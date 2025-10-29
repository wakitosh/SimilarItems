<?php

/**
 * @file
 */

declare(strict_types=1);

namespace SimilarItems;

use SimilarItems\Site\ResourcePageBlockLayout\SimilarItems as SimilarItemsBlock;

/**
 * @file
 * Module configuration for SimilarItems.
 */
return [
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
  ],
];
