<?php

declare(strict_types=1);

namespace SimilarItems\Site\ResourcePageBlockLayout;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Site\ResourcePageBlockLayout\ResourcePageBlockLayoutInterface;
use SimilarItems\Experiment\PlacementAssigner;

/**
 * Resource page block: similar items as a single row of five.
 *
 * Meant for the full-width region directly below the viewer, where it reaches
 * the screen as soon as the visitor scrolls at all. The sidebar block sits below
 * the viewer and after the export and download panels, and was measured to be
 * seen on about one page view in seven.
 */
class SimilarItemsStrip implements ResourcePageBlockLayoutInterface {

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
    return 'Similar items (row below the viewer)';
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
    if ($this->placements && !$this->placements->shows(PlacementAssigner::STRIP)) {
      return '';
    }
    return $view->partial('common/resource-page-blocks/similar-items-strip', [
      'resource' => $resource,
    ]);
  }

}
