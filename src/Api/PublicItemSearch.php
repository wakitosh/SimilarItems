<?php

/**
 * @file
 * Restricts candidate lookups to items the public can actually reach.
 */

declare(strict_types=1);

namespace SimilarItems\Api;

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
   * Search, forcing item queries to public items only.
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
    if ($resource === 'items') {
      // An explicit is_public in the query is left alone: a caller asking for
      // something specific is not second-guessed.
      if (!array_key_exists('is_public', $query)) {
        $query['is_public'] = TRUE;
      }
    }
    return $this->api->search($resource, $query, ...$args);
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
