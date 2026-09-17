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
 * Applied in one place rather than on each query, so that a new lookup added to
 * the scoring engine cannot silently omit it.
 */
class PublicItemSearch {

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
    $response = $this->api->search($resource, $query, ...$args);
    if ($resource !== 'items') {
      return $response;
    }
    // An explicit is_public in the query is left alone: a caller asking for
    // something specific is not second-guessed.
    if (array_key_exists('is_public', $query)) {
      return $response;
    }
    return $this->withoutNonPublic($response);
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
