<?php

/**
 * @file
 * Restricts candidate lookups to items the public can actually reach.
 */

declare(strict_types=1);

namespace SimilarItems\Api;

use Omeka\Api\Response;

/**
 * Wraps the API helper so item searches only ever return public items.
 *
 * The recommendation block lives on public site pages. Its job is to answer
 * "what else might a visitor want", so a non-public item is never a valid
 * answer: nobody browsing the site can reach it.
 *
 * Without this, the result depended on who was looking. Omeka returns whatever
 * the current identity may read, so a signed-in editor or administrator was
 * shown recommendations drawn from a larger pool than visitors get - which
 * misleads staff reviewing the feature, since they judge a list no visitor will
 * see, and it mixes two incomparable populations in the usage log.
 *
 * The restriction is applied to the results rather than to the query. Asking
 * Omeka for `is_public` instead looks equivalent but is not: for an anonymous
 * visitor the visibility filter has already put `is_public = 1` in the JOIN
 * condition, so the extra term is a harmless duplicate, whereas for a user
 * holding `view-all` that filter is switched off and the term lands alone in
 * the WHERE clause, where it costs a far worse query plan - measured at 3.0 s
 * against 0.12 s for one property lookup on the production catalogue, and the
 * engine issues one such lookup per mapped value on the seed item. Discarding
 * the few non-public rows afterwards reaches the same answer at no cost: on
 * that catalogue 17 of 36,252 items attached to the public sites are
 * non-public.
 *
 * Each search is also split in two. Omeka counts every search with a second
 * query that has no LIMIT, and builds a representation for every row; the
 * engine reads only the rows and never the total, so both were being paid for
 * nothing. Asking for bare ids first and then loading exactly that set costs
 * about half as much - 1.9x measured end to end over the gathering phase, with
 * the same items in the same order - and the count is deferred to whoever
 * actually asks for it.
 *
 * Applied in one place rather than on each query, so that a new lookup added to
 * the scoring engine cannot silently omit it.
 */
class PublicItemSearch {

  /**
   * Searches asking for at most this many rows are passed straight through.
   *
   * The engine probes for a total by asking for a single row and reading the
   * count; splitting such a search would save nothing and would only move the
   * count it exists to obtain.
   */
  private const PASSTHROUGH_LIMIT = 1;

  /**
   * The API view helper being wrapped.
   *
   * @var mixed
   */
  private $api;

  /**
   * Constructor.
   *
   * @param mixed $api
   *   Omeka's API view helper, or anything exposing search()/read().
   */
  public function __construct($api) {
    $this->api = $api;
  }

  /**
   * Search, dropping any non-public item from the results.
   *
   * @param string $resource
   *   API resource name.
   * @param array $query
   *   Search query.
   * @param mixed ...$args
   *   Further arguments passed through unchanged.
   *
   * @return mixed
   *   The API response.
   */
  public function search($resource, array $query = [], ...$args) {
    if ($resource !== 'items') {
      return $this->api->search($resource, $query, ...$args);
    }
    // An explicit is_public in the query is left alone: a caller asking for
    // something specific is not second-guessed. A caller already asking for
    // scalars wants exactly that, and gets no representations to inspect.
    if (array_key_exists('is_public', $query) || isset($query['return_scalar'])) {
      return $this->api->search($resource, $query, ...$args);
    }
    if (isset($query['limit']) && (int) $query['limit'] <= self::PASSTHROUGH_LIMIT) {
      return $this->withoutNonPublic($this->api->search($resource, $query, ...$args));
    }
    return $this->searchByIds($resource, $query, $args);
  }

  /**
   * Fetch ids first, then load exactly that set of items.
   *
   * @param string $resource
   *   API resource name.
   * @param array $query
   *   Search query.
   * @param array $args
   *   Further arguments passed through unchanged.
   *
   * @return mixed
   *   A response holding the public items, in the order the query produced.
   */
  private function searchByIds($resource, array $query, array $args) {
    $scalar = $this->api->search($resource, $query + ['return_scalar' => 'id'], ...$args);
    $ids = [];
    foreach ((array) $scalar->getContent() as $id) {
      $ids[] = (int) $id;
    }
    $total = function () use ($resource, $query, $args) {
      return $this->countFor($resource, $query, $args);
    };
    if (!$ids) {
      return new DeferredCountResponse([], $total);
    }
    // The id set is already scoped by the first search, so the second one adds
    // no conditions of its own: it exists only to build the representations.
    $loaded = $this->api->search($resource, ['id' => $ids, 'limit' => count($ids)], ...$args);
    $byId = [];
    foreach ((array) $loaded->getContent() as $item) {
      if (is_object($item) && method_exists($item, 'id')) {
        $byId[(int) $item->id()] = $item;
      }
    }
    $ordered = [];
    foreach ($ids as $id) {
      // An id with no item behind it means the row went away between the two
      // queries. Skipping it is the same outcome as never having matched.
      if (!isset($byId[$id])) {
        continue;
      }
      $item = $byId[$id];
      if (method_exists($item, 'isPublic') && !$item->isPublic()) {
        continue;
      }
      $ordered[] = $item;
    }
    return new DeferredCountResponse($ordered, $total);
  }

  /**
   * Ask Omeka how many rows the query matches in total.
   *
   * @param string $resource
   *   API resource name.
   * @param array $query
   *   Search query.
   * @param array $args
   *   Further arguments passed through unchanged.
   *
   * @return int
   *   The total, which counts non-public rows as Omeka would have counted them.
   */
  private function countFor($resource, array $query, array $args): int {
    $query['limit'] = 1;
    unset($query['page'], $query['per_page'], $query['offset']);
    $response = $this->api->search($resource, $query, ...$args);
    return method_exists($response, 'getTotalResults') ? (int) $response->getTotalResults() : 0;
  }

  /**
   * Rebuild a response without the items the public cannot reach.
   *
   * @param mixed $response
   *   The response returned by the wrapped helper.
   *
   * @return mixed
   *   The original response when nothing was removed, otherwise a copy holding
   *   only the public items.
   */
  private function withoutNonPublic($response) {
    if (!is_object($response) || !method_exists($response, 'getContent')) {
      return $response;
    }
    $content = $response->getContent();
    if (!is_array($content)) {
      return $response;
    }
    $kept = [];
    foreach ($content as $key => $item) {
      // Anything that cannot report its visibility is kept: a scalar id list
      // (return_scalar) carries no such flag, and guessing would be wrong.
      if (is_object($item) && method_exists($item, 'isPublic') && !$item->isPublic()) {
        continue;
      }
      $kept[$key] = $item;
    }
    $removed = count($content) - count($kept);
    if ($removed === 0) {
      return $response;
    }
    $filtered = new Response($kept);
    if (method_exists($response, 'getTotalResults')) {
      // The total covers rows beyond this page, which have not been inspected.
      // Subtracting only what was removed here keeps it an upper bound rather
      // than an invented figure.
      $filtered->setTotalResults(max(0, (int) $response->getTotalResults() - $removed));
    }
    return $filtered;
  }

  /**
   * Pass anything else straight through.
   *
   * @param string $name
   *   Method name.
   * @param array $arguments
   *   Arguments.
   *
   * @return mixed
   *   Whatever the wrapped helper returns.
   */
  public function __call($name, array $arguments) {
    return $this->api->{$name}(...$arguments);
  }

}
