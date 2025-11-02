<?php

/**
 * @file
 * Module configuration for SimilarItems.
 */

declare(strict_types=1);

namespace SimilarItems;

use SimilarItems\Service\ViewHelper\SimilarItemsFactory;
use SimilarItems\Site\ResourcePageBlockLayout\SimilarItems as SimilarItemsBlock;
use SimilarItems\View\Helper\SimilarItems as SimilarItemsHelper;
use SimilarItems\Controller\RecommendController;

return [
  'controllers' => [
    'invokables' => [
      RecommendController::class => RecommendController::class,
    ],
  ],
  'router' => [
    'routes' => [
      'similaritems-recommend' => [
        'type' => 'Literal',
        'options' => [
          'route' => '/similar-items/recommend',
          'defaults' => [
            'controller' => RecommendController::class,
            'action' => 'list',
          ],
        ],
      ],
      // Site-aware endpoint so the public site's theme (and its view overrides)
      // can be applied when rendering the partial. This mirrors the site URL
      // structure and sets the __SITE__ flag so Omeka prepares the site/theme.
      'similaritems-recommend-site' => [
        'type' => 'Segment',
        'options' => [
          'route' => '/s/:site-slug/similar-items/recommend',
          'defaults' => [
            'controller' => RecommendController::class,
            'action' => 'list',
            '__SITE__' => TRUE,
          ],
        ],
      ],
    ],
  ],
  'view_helpers' => [
    'factories' => [
      SimilarItemsHelper::class => SimilarItemsFactory::class,
    ],
    'aliases' => [
      // Callable from views as $this->similarItems($resource, $options)
      'similarItems' => SimilarItemsHelper::class,
    ],
  ],
  'resource_page_block_layouts' => [
    'invokables' => [
      // Register as "similarItems" to match theme's default placement key.
      'similarItems' => SimilarItemsBlock::class,
    ],
  ],
  'view_manager' => [
    'template_path_stack' => [
      __DIR__ . '/../view',
    ],
    'strategies' => [
      'ViewJsonStrategy',
    ],
  ],
];
