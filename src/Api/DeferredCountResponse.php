<?php

/**
 * @file
 * An API response whose total is only counted if somebody asks for it.
 */

declare(strict_types=1);

namespace SimilarItems\Api;

use Omeka\Api\Response;

/**
 * Carries search results whose total result count is resolved on demand.
 *
 * Omeka counts every search it runs, with a second query that has no LIMIT. The
 * recommendation engine gathers candidates through several searches per request
 * and reads only their contents, so those counts are computed and thrown away -
 * measured on the production catalogue at a third to a half of the whole
 * response. Deferring the count means the engine stops paying for it, while a
 * caller that does want a total still gets a true one.
 */
class DeferredCountResponse extends Response {

  /**
   * Produces the total on first use, or NULL once it has been resolved.
   *
   * @var callable|null
   */
  private $resolver;

  /**
   * Constructor.
   *
   * @param array $content
   *   The search results.
   * @param callable $resolver
   *   Returns the total number of results the query would match.
   */
  public function __construct(array $content, callable $resolver) {
    parent::__construct($content);
    $this->resolver = $resolver;
  }

  /**
   * {@inheritdoc}
   */
  public function getTotalResults() {
    if ($this->resolver !== NULL) {
      $resolver = $this->resolver;
      // Cleared first: a resolver that throws must not be run again on a
      // second call, and must not leave the total permanently unset.
      $this->resolver = NULL;
      $this->setTotalResults((int) $resolver());
    }
    return parent::getTotalResults();
  }

  /**
   * {@inheritdoc}
   */
  public function setTotalResults($totalResults) {
    $this->resolver = NULL;
    parent::setTotalResults($totalResults);
  }

}
