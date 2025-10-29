<?php

declare(strict_types=1);

namespace SimilarItems\Site\ResourcePageBlockLayout;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Site\ResourcePageBlockLayout\ResourcePageBlockLayoutInterface;

/**
 * Resource page block: Similar items (experimental).
 */
class SimilarItems implements ResourcePageBlockLayoutInterface {

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
    // Delegate to the theme partial so the theme can control layout/styling.
    return $view->partial('common/resource-page-blocks/similar-items', [
      'resource' => $resource,
    ]);
  }

}
