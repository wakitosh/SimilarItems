<?php

declare(strict_types=1);

namespace SimilarItems\Site\ResourcePageBlockLayout;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Site\ResourcePageBlockLayout\ResourcePageBlockLayoutInterface;
use SimilarItems\Experiment\PlacementAssigner;

/**
 * Resource page block: similar items behind a floating button.
 *
 * A character fixed to the bottom-right corner of the window opens a small
 * panel with the recommendations, in the manner of a chat widget. The block
 * takes no room in the page flow, so the region it is assigned to only decides
 * that it is rendered: the main region is recommended, since it is present on
 * every item page and adding a block to a sidebar can create the column.
 */
class SimilarItemsFloating implements ResourcePageBlockLayoutInterface {

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
    return 'Similar items (floating button)';
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
    if ($this->placements && !$this->placements->shows(PlacementAssigner::FLOATING)) {
      return '';
    }
    return $view->partial('common/resource-page-blocks/similar-items-floating', [
      'resource' => $resource,
    ]);
  }

}
