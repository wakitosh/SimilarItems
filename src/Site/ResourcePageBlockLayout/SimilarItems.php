<?php

declare(strict_types=1);

namespace SimilarItems\Site\ResourcePageBlockLayout;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Site\ResourcePageBlockLayout\ResourcePageBlockLayoutInterface;
use SimilarItems\Experiment\PlacementAssigner;

/**
 * Resource page block: Similar items (experimental).
 */
class SimilarItems implements ResourcePageBlockLayoutInterface {

  /**
   * Placement trial: decides whether this block renders for the visitor.
   */
  private ?PlacementAssigner $placements;

  /**
   * Constructor.
   */
  public function __construct(?PlacementAssigner $placements = NULL) {
    $this->placements = $placements;
  }

  /**
   * {@inheritDoc}
   */
  public function getLabel(): string {
    // @translate
    return 'Similar items';
  }

  /**
   * {@inheritDoc}
   */
  public function getCompatibleResourceNames(): array {
    return [
      'items',
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function render(PhpRenderer $view, AbstractResourceEntityRepresentation $resource): string {
    // During the placement trial only the visitor's assigned block renders.
    if ($this->placements && !$this->placements->shows(PlacementAssigner::SIDEBAR)) {
      return '';
    }
    // Delegate to the theme partial so the theme can control layout/styling.
    return $view->partial('common/resource-page-blocks/similar-items', [
      'resource' => $resource,
    ]);
  }

}
