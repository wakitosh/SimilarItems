<?php

/**
 * @file
 * Module configuration for SimilarItems.
 */

declare(strict_types=1);

namespace SimilarItems;

use Laminas\Router\Http\Literal;
use Laminas\Router\Http\Segment;
use SimilarItems\Controller\Admin\LogsController;
use SimilarItems\Controller\EventController;
use SimilarItems\Controller\RecommendController;
use SimilarItems\Experiment\ArmAssigner;
use SimilarItems\Log\LogService;
use SimilarItems\Service\Log\LogServiceFactory;
use SimilarItems\Service\ViewHelper\SimilarItemsFactory;
use SimilarItems\Site\ResourcePageBlockLayout\SimilarItems as SimilarItemsBlock;
use SimilarItems\View\Helper\SimilarItems as SimilarItemsHelper;

return [
  'service_manager' => [
    'factories' => [
      LogService::class => LogServiceFactory::class,
      ArmAssigner::class => function ($container) {
        return new ArmAssigner($container->get('Omeka\Settings'));
      },
    ],
  ],
  'controllers' => [
    'factories' => [
      RecommendController::class => function ($container) {
        return new RecommendController(
          $container->get(LogService::class),
          $container->get(ArmAssigner::class)
        );
      },
      EventController::class => function ($container) {
        return new EventController($container->get(LogService::class));
      },
      LogsController::class => function ($container) {
        return new LogsController(
          $container->get(LogService::class),
          $container->get(ArmAssigner::class)
        );
      },
    ],
  ],
  'navigation' => [
    'AdminModule' => [
      [
        // @translate
        'label' => 'Similar Items logs',
        'route' => 'admin/similar-items-logs',
        'resource' => LogsController::class,
        'privilege' => 'index',
      ],
    ],
  ],
  'router' => [
    'routes' => [
      'similaritems-recommend' => [
        'type' => Literal::class,
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
        'type' => Segment::class,
        'options' => [
          'route' => '/s/:site-slug/similar-items/recommend',
          'defaults' => [
            'controller' => RecommendController::class,
            'action' => 'list',
            '__SITE__' => TRUE,
          ],
        ],
      ],
      // Usage-log collection endpoints (public, POST only).
      'similaritems-event' => [
        'type' => Literal::class,
        'options' => [
          'route' => '/similar-items/event',
          'defaults' => [
            'controller' => EventController::class,
            'action' => 'collect',
          ],
        ],
      ],
      'similaritems-event-site' => [
        'type' => Segment::class,
        'options' => [
          'route' => '/s/:site-slug/similar-items/event',
          'defaults' => [
            'controller' => EventController::class,
            'action' => 'collect',
            '__SITE__' => TRUE,
          ],
        ],
      ],
      'admin' => [
        'child_routes' => [
          'similar-items-logs' => [
            'type' => Segment::class,
            'options' => [
              'route' => '/similar-items/logs',
              'defaults' => [
                '__NAMESPACE__' => 'SimilarItems\\Controller\\Admin',
                'controller' => LogsController::class,
                'action' => 'index',
              ],
            ],
          ],
          'similar-items-logs-help' => [
            'type' => Segment::class,
            'options' => [
              'route' => '/similar-items/logs/help',
              'defaults' => [
                '__NAMESPACE__' => 'SimilarItems\\Controller\\Admin',
                'controller' => LogsController::class,
                'action' => 'help',
              ],
            ],
          ],
          'similar-items-logs-impressions' => [
            'type' => Segment::class,
            'options' => [
              'route' => '/similar-items/logs/impressions',
              'defaults' => [
                '__NAMESPACE__' => 'SimilarItems\\Controller\\Admin',
                'controller' => LogsController::class,
                'action' => 'impressions',
              ],
            ],
          ],
          'similar-items-logs-events' => [
            'type' => Segment::class,
            'options' => [
              'route' => '/similar-items/logs/events',
              'defaults' => [
                '__NAMESPACE__' => 'SimilarItems\\Controller\\Admin',
                'controller' => LogsController::class,
                'action' => 'events',
              ],
            ],
          ],
          'similar-items-logs-export' => [
            'type' => Segment::class,
            'options' => [
              'route' => '/similar-items/logs/export',
              'defaults' => [
                '__NAMESPACE__' => 'SimilarItems\\Controller\\Admin',
                'controller' => LogsController::class,
                'action' => 'export',
              ],
            ],
          ],
          'similar-items-logs-clear' => [
            'type' => Segment::class,
            'options' => [
              'route' => '/similar-items/logs/clear',
              'defaults' => [
                '__NAMESPACE__' => 'SimilarItems\\Controller\\Admin',
                'controller' => LogsController::class,
                'action' => 'clear',
              ],
            ],
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
