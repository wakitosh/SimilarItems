<?php

declare(strict_types=1);

namespace SimilarItems;

use Omeka\Module\AbstractModule;
use Laminas\View\Renderer\PhpRenderer;
use Laminas\Mvc\Controller\AbstractController;
use Laminas\Mvc\MvcEvent;
use SimilarItems\Controller\RecommendController as RecommendControllerAlias;
use SimilarItems\Form\ConfigForm;
use Laminas\ServiceManager\ServiceLocatorInterface;

/**
 * Minimal bootstrap for the SimilarItems module.
 */
class Module extends AbstractModule {

  /**
   * Return module configuration.
   */
  public function getConfig(): array {
    return include __DIR__ . '/config/module.config.php';
  }

  /**
   * Register ACL so the recommend endpoint is publicly accessible.
   */
  public function onBootstrap(MvcEvent $event): void {
    $services = $event->getApplication()->getServiceManager();
    /** @var \Omeka\Permissions\Acl $acl */
    $acl = $services->get('Omeka\Acl');

    // Allow all roles (including anonymous visitors) to access the list action
    // on the recommend controller. This enables public sites to fetch
    // recommendations without requiring login.
    $acl->allow(NULL, [RecommendControllerAlias::class], ['list']);

    // Also allow controller alias resource.
    $acl->allow(NULL, ['SimilarItems\\Controller\\RecommendController'], ['list']);
  }

  /**
   * PSR-4 autoloader configuration.
   */
  public function getAutoloaderConfig(): array {
    return [
      'Laminas\\Loader\\StandardAutoloader' => [
        'namespaces' => [
          __NAMESPACE__ => __DIR__ . '/src',
        ],
      ],
    ];
  }

  /**
   * Render module configuration form (global settings, not per-site).
   */
  public function getConfigForm(PhpRenderer $renderer) {
    // Acquire the application container without using deprecated APIs.
    // 1) Prefer the HelperPluginManager creation context (Laminas v3+)
    // 2) Fallback to HPM->getServiceLocator() (suppressed deprecation),
    // 3) Finally, try the module's service locator as a last resort.
    $hpm = $renderer->getHelperPluginManager();
    $services = NULL;
    if (method_exists($hpm, 'getCreationContext')) {
      $ctx = $hpm->getCreationContext();
      if ($ctx) {
        $services = $ctx;
      }
    }
    if (!$services && method_exists($hpm, 'getServiceLocator')) {
      // Suppress deprecation notice at runtime while preserving compatibility.
      $services = @$hpm->getServiceLocator();
    }
    if (!$services && method_exists($this, 'getServiceLocator')) {
      $services = $this->getServiceLocator();
    }
    if (!$services) {
      throw new \RuntimeException('Cannot access application container to render SimilarItems config form.');
    }
    $settings = $services->get('Omeka\Settings');
    $form = $services->get('FormElementManager')->get(ConfigForm::class);

    // Populate defaults from settings.
    $form->setData([
      'similaritems_scope_site' => (int) ($settings->get('similaritems.scope_site') ?? 1),
      'similaritems_use_item_sets' => (int) ($settings->get('similaritems.use_item_sets') ?? 1),
      'similaritems_limit' => (int) ($settings->get('similaritems.limit') ?? 6),
      'similaritems_debug_log' => (int) ($settings->get('similaritems.debug_log') ?? 0),
      // Tie-break policy.
      'similaritems_tiebreak_policy' => (string) ($settings->get('similaritems.tiebreak_policy') ?? 'none'),
      // Jitter.
      'similaritems_jitter_enable' => (int) ($settings->get('similaritems.jitter.enable') ?? 0),
      'similaritems_jitter_pool_multiplier' => (string) ($settings->get('similaritems.jitter.pool_multiplier') ?? '1.5'),

      // Property mapping (advanced)
      'similaritems_map_call_number' => (string) ($settings->get('similaritems.map.call_number') ?? 'dcndl:callNumber'),
      'similaritems_map_class_number' => (string) ($settings->get('similaritems.map.class_number') ?? 'dc:subject'),
      'similaritems_map_bibid' => (string) ($settings->get('similaritems.map.bibid') ?? 'tsukubada:bibid'),
      'similaritems_map_author_id' => (string) ($settings->get('similaritems.map.author_id') ?? 'tsukubada:authorId'),
      'similaritems_map_authorized_name' => (string) ($settings->get('similaritems.map.authorized_name') ?? 'tsukubada:authorizedName'),
      'similaritems_map_location' => (string) ($settings->get('similaritems.map.location') ?? 'dcndl:location'),
      'similaritems_map_issued' => (string) ($settings->get('similaritems.map.issued') ?? 'dcterms:issued'),
      'similaritems_map_material_type' => (string) ($settings->get('similaritems.map.material_type') ?? 'dcndl:materialType'),
      'similaritems_map_viewing_direction' => (string) ($settings->get('similaritems.map.viewing_direction') ?? 'tsukubada:viewingDirection'),
      'similaritems_map_subject' => (string) ($settings->get('similaritems.map.subject') ?? ''),
      'similaritems_map_series_title' => (string) ($settings->get('similaritems.map.series_title') ?? ''),
      'similaritems_map_publisher' => (string) ($settings->get('similaritems.map.publisher') ?? ''),

      // Weights (basic set).
      'similaritems_weight_bibid' => (int) ($settings->get('similaritems.weight.bibid') ?? 0),
      'similaritems_weight_author_id' => (int) ($settings->get('similaritems.weight.author_id') ?? 6),
      'similaritems_weight_authorized_name' => (int) ($settings->get('similaritems.weight.authorized_name') ?? 4),
      'similaritems_weight_subject' => (int) ($settings->get('similaritems.weight.subject') ?? 4),
      'similaritems_weight_domain_bucket' => (int) ($settings->get('similaritems.weight.domain_bucket') ?? 3),
      'similaritems_weight_call_shelf' => (int) ($settings->get('similaritems.weight.call_shelf') ?? 2),
      'similaritems_weight_series_title' => (int) ($settings->get('similaritems.weight.series_title') ?? 3),
      'similaritems_weight_publisher' => (int) ($settings->get('similaritems.weight.publisher') ?? 2),
      'similaritems_weight_item_sets' => (int) ($settings->get('similaritems.weight_item_sets') ?? 3),
      'similaritems_weight_class_proximity' => (int) ($settings->get('similaritems.weight.class_proximity') ?? 1),
      'similaritems_weight_class_exact' => (int) ($settings->get('similaritems.weight.class_exact') ?? 2),
      'similaritems_weight_material_type' => (int) ($settings->get('similaritems.weight.material_type') ?? 2),
      'similaritems_weight_issued_proximity' => (int) ($settings->get('similaritems.weight.issued_proximity') ?? 1),
      'similaritems_weight_publication_place' => (int) ($settings->get('similaritems.weight.publication_place') ?? 1),
      'similaritems_issued_proximity_threshold' => (int) ($settings->get('similaritems.issued_proximity_threshold') ?? 5),
      'similaritems_class_proximity_threshold' => (int) ($settings->get('similaritems.class_proximity_threshold') ?? 5),

      // Domain bucket rules JSON.
      'similaritems_bucket_rules' => (string) ($settings->get('similaritems.bucket_rules') ?? $this->getDefaultBucketRules()),

      // Serendipity options.
      'similaritems_serendipity_demote_same_bibid' => (int) ($settings->get('similaritems.serendipity.demote_same_bibid') ?? 1),
      'similaritems_same_bibid_penalty' => (int) ($settings->get('similaritems.serendipity.same_bibid_penalty') ?? 150),
      'similaritems_same_title_penalty' => (int) ($settings->get('similaritems.serendipity.same_title_penalty') ?? 150),
      'similaritems_serendipity_same_title_mode' => (string) ($settings->get('similaritems.serendipity.same_title_mode') ?? 'allow'),

      // Title rules.
      'similaritems_title_volume_separators' => (string) ($settings->get('similaritems.title_volume_separators') ?? ' , '),

      // Multi-match bonus (global).
      'similaritems_multi_match_enable' => (int) ($settings->get('similaritems.multi_match.enable') ?? 0),
      'similaritems_multi_match_decay' => (string) ($settings->get('similaritems.multi_match.decay') ?? '0.2'),
    ]);

    $form->prepare();

    // Add admin-only inline styles scoped to this module's config page.
    // Fieldset: top margin 20px (first only 0), bottom margin 0.
    $renderer->headStyle()->appendStyle(
      '#similaritems-config fieldset { margin-top: 20px; margin-bottom: 0; }' .
      '#similaritems-config fieldset:first-of-type { margin-top: 0; }'
    );

    // Wrap markup in a scoped container to avoid leaking styles globally.
    return '<div id="similaritems-config">' . $renderer->formCollection($form) . '</div>';
  }

  /**
   * Set default settings on module install so they persist as initial values.
   */
  public function install(ServiceLocatorInterface $services): void {
    $settings = $services->get('Omeka\Settings');

    // Only set defaults if not already set.
    $defaults = [
      'similaritems.scope_site' => 1,
      'similaritems.use_item_sets' => 1,
      'similaritems.limit' => 6,
      'similaritems.weight_item_sets' => 3,
      'similaritems.debug_log' => 0,
      // Tie-break policy default.
      'similaritems.tiebreak_policy' => 'none',

      // Property mapping defaults.
      'similaritems.map.call_number' => 'dcndl:callNumber',
      'similaritems.map.class_number' => 'dc:subject',
      'similaritems.map.bibid' => 'tsukubada:bibid',
      'similaritems.map.author_id' => 'tsukubada:authorId',
      'similaritems.map.authorized_name' => 'tsukubada:authorizedName',
      'similaritems.map.location' => 'dcndl:location',
      'similaritems.map.issued' => 'dcterms:issued',
      'similaritems.map.material_type' => 'dcndl:materialType',
      'similaritems.map.viewing_direction' => 'tsukubada:viewingDirection',
      'similaritems.map.subject' => '',
      'similaritems.map.series_title' => '',
      'similaritems.map.publisher' => '',

      // Weights (basic set) defaults.
      'similaritems.weight.bibid' => 0,
      'similaritems.weight.author_id' => 6,
      'similaritems.weight.authorized_name' => 4,
      'similaritems.weight.subject' => 4,
      'similaritems.weight.domain_bucket' => 3,
      'similaritems.weight.call_shelf' => 2,
      'similaritems.weight.series_title' => 3,
      'similaritems.weight.publisher' => 2,
      'similaritems.weight.class_proximity' => 1,
      'similaritems.weight.class_exact' => 2,
      'similaritems.weight.material_type' => 2,
      'similaritems.weight.issued_proximity' => 1,
      'similaritems.weight.publication_place' => 1,
      'similaritems.class_proximity_threshold' => 5,
      'similaritems.issued_proximity_threshold' => 5,

      // Domain bucket rules JSON default.
      'similaritems.bucket_rules' => $this->getDefaultBucketRules(),

      // Serendipity defaults.
      'similaritems.serendipity.demote_same_bibid' => 1,
      'similaritems.serendipity.same_bibid_penalty' => 150,
      'similaritems.serendipity.same_title_penalty' => 150,
      'similaritems.serendipity.same_title_mode' => 'allow',

      // Title rules defaults.
      'similaritems.title_volume_separators' => ' , ',
      'similaritems.title_max_length' => 60,
      // Jitter defaults.
      'similaritems.jitter.enable' => 0,
      'similaritems.jitter.pool_multiplier' => '1.5',

      // Multi-match defaults.
      'similaritems.multi_match.enable' => 0,
      'similaritems.multi_match.decay' => '0.2',
    ];

    foreach ($defaults as $key => $value) {
      $current = $settings->get($key);
      if ($current === NULL) {
        $settings->set($key, $value);
      }
    }
  }

  /**
   * Save module configuration form values to global settings.
   */
  public function handleConfigForm(AbstractController $controller) {
    $services = $controller->getEvent()->getApplication()->getServiceManager();
    $settings = $services->get('Omeka\Settings');
    $post = $controller->params()->fromPost();

    $getInt = function ($key, $def = 0) use ($post) {
      return (int) ($post[$key] ?? $def);
    };
    $getStr = function ($key, $def = '') use ($post) {
      return (string) ($post[$key] ?? $def);
    };

    $settings->set('similaritems.scope_site', $getInt('similaritems_scope_site', 1));
    $settings->set('similaritems.use_item_sets', $getInt('similaritems_use_item_sets', 1));
    $settings->set('similaritems.limit', max(1, $getInt('similaritems_limit', 6)));
    $settings->set('similaritems.weight_item_sets', $getInt('similaritems_weight_item_sets', 3));
    $settings->set('similaritems.debug_log', $getInt('similaritems_debug_log', 0));
    // Tie-break policy.
    $tbPolicy = strtolower($getStr('similaritems_tiebreak_policy', 'none'));
    if (!in_array($tbPolicy, ['none', 'consensus', 'strength', 'identity'], TRUE)) {
      $tbPolicy = 'none';
    }
    $settings->set('similaritems.tiebreak_policy', $tbPolicy);
    // Jitter.
    $settings->set('similaritems.jitter.enable', $getInt('similaritems_jitter_enable', 0));
    $poolMulRaw = $getStr('similaritems_jitter_pool_multiplier', '1.5');
    // Sanitize numeric (float) but store as string to preserve formatting.
    $poolMul = (float) $poolMulRaw;
    if (!is_finite($poolMul) || $poolMul < 1.0) {
      $poolMul = 1.0;
    }
    $settings->set('similaritems.jitter.pool_multiplier', (string) $poolMul);

    // Save property mapping (advanced).
    $settings->set('similaritems.map.call_number', $getStr('similaritems_map_call_number', ''));
    $settings->set('similaritems.map.class_number', $getStr('similaritems_map_class_number', ''));
    $settings->set('similaritems.map.bibid', $getStr('similaritems_map_bibid', ''));
    $settings->set('similaritems.map.author_id', $getStr('similaritems_map_author_id', ''));
    $settings->set('similaritems.map.authorized_name', $getStr('similaritems_map_authorized_name', ''));
    $settings->set('similaritems.map.location', $getStr('similaritems_map_location', ''));
    $settings->set('similaritems.map.issued', $getStr('similaritems_map_issued', ''));
    $settings->set('similaritems.map.material_type', $getStr('similaritems_map_material_type', ''));
    $settings->set('similaritems.map.viewing_direction', $getStr('similaritems_map_viewing_direction', ''));
    $settings->set('similaritems.map.subject', $getStr('similaritems_map_subject', ''));
    $settings->set('similaritems.map.series_title', $getStr('similaritems_map_series_title', ''));
    $settings->set('similaritems.map.publisher', $getStr('similaritems_map_publisher', ''));

    // Save weights (basic set).
    $settings->set('similaritems.weight.bibid', $getInt('similaritems_weight_bibid', 10));
    $settings->set('similaritems.weight.author_id', $getInt('similaritems_weight_author_id', 6));
    $settings->set('similaritems.weight.authorized_name', $getInt('similaritems_weight_authorized_name', 4));
    $settings->set('similaritems.weight.domain_bucket', $getInt('similaritems_weight_domain_bucket', 3));
    $settings->set('similaritems.weight.call_shelf', $getInt('similaritems_weight_call_shelf', 2));
    $settings->set('similaritems.weight.series_title', $getInt('similaritems_weight_series_title', 3));
    $settings->set('similaritems.weight.publisher', $getInt('similaritems_weight_publisher', 2));
    $settings->set('similaritems.weight.class_proximity', $getInt('similaritems_weight_class_proximity', 1));
    $settings->set('similaritems.weight.class_exact', $getInt('similaritems_weight_class_exact', 2));
    $settings->set('similaritems.class_proximity_threshold', max(0, $getInt('similaritems_class_proximity_threshold', 5)));
    $settings->set('similaritems.weight.subject', $getInt('similaritems_weight_subject', 4));
    $settings->set('similaritems.weight.material_type', $getInt('similaritems_weight_material_type', 2));
    $settings->set('similaritems.weight.issued_proximity', $getInt('similaritems_weight_issued_proximity', 1));
    $settings->set('similaritems.weight.publication_place', $getInt('similaritems_weight_publication_place', 1));
    $settings->set('similaritems.issued_proximity_threshold', max(0, $getInt('similaritems_issued_proximity_threshold', 5)));

    // Save bucket rules JSON (no validation here; UI can include a test tool).
    $settings->set('similaritems.bucket_rules', $getStr('similaritems_bucket_rules', ''));

    // Serendipity options.
    $settings->set('similaritems.serendipity.demote_same_bibid', $getInt('similaritems_serendipity_demote_same_bibid', 1));
    $settings->set('similaritems.serendipity.same_bibid_penalty', max(0, $getInt('similaritems_same_bibid_penalty', 100)));
    $settings->set('similaritems.serendipity.same_title_penalty', max(0, $getInt('similaritems_same_title_penalty', 150)));
    $sameTitleMode = $getStr('similaritems_serendipity_same_title_mode', 'allow');
    if (!in_array($sameTitleMode, ['allow', 'exclude', 'exclude_no_fallback'], TRUE)) {
      $sameTitleMode = 'allow';
    }
    $settings->set('similaritems.serendipity.same_title_mode', $sameTitleMode);

    // Title rules.
    $settings->set('similaritems.title_volume_separators', $getStr('similaritems_title_volume_separators', ' , '));

    // Multi-match bonus.
    $settings->set('similaritems.multi_match.enable', $getInt('similaritems_multi_match_enable', 0));
    $decayRaw = $getStr('similaritems_multi_match_decay', '0.2');
    $decay = (float) $decayRaw;
    if (!is_finite($decay) || $decay < 0.0) {
      $decay = 0.0;
    }
    $settings->set('similaritems.multi_match.decay', (string) $decay);

    $controller->messenger()->addSuccess('SimilarItems settings were saved.');
    return TRUE;
  }

  /**
   * Default domain bucket rules as JSON string.
   */
  private function getDefaultBucketRules(): string {
    return '{
  "fields": {"call_number": "call_number", "class_number": "class_number"},
  "buckets": [
    {"key": "general", "labels": {"ja": "総記"},
     "any": [
       {"field": "call_number", "op": "prefix", "value": "0"},
       {"field": "call_number", "op": "prefix", "value": "イ"},
       {"field": "call_number", "op": "prefix", "value": "A"}
     ]},
    {"key": "philosophy", "labels": {"ja": "哲学"},
     "any": [
       {"field": "call_number", "op": "prefix", "value": "1"},
       {"field": "call_number", "op": "prefix", "value": "ロ"},
       {"field": "call_number", "op": "prefix", "value": "ハ"},
       {"field": "call_number", "op": "prefix", "value": "B"},
       {"field": "call_number", "op": "prefix", "value": "C"}
     ]},
    {"key": "history", "labels": {"ja": "歴史"},
     "any": [
       {"field": "call_number", "op": "prefix", "value": "2"},
       {"field": "call_number", "op": "prefix", "value": "ヨ"},
       {"field": "call_number", "op": "prefix", "value": "タ"},
       {"field": "call_number", "op": "prefix", "value": "ネ"},
       {"field": "call_number", "op": "prefix", "value": "東亜研"},
       {"field": "call_number", "op": "prefix", "value": "H"},
       {"field": "call_number", "op": "prefix", "value": "J"},
       {"field": "call_number", "op": "prefix", "value": "K"},
       {"field": "call_number", "op": "contains", "value": "雑文書"},
       {"field": "call_number", "op": "contains", "value": "昌平坂"},
       {"field": "call_number", "op": "contains", "value": "石清水"},
       {"field": "call_number", "op": "contains", "value": "大徳寺"},
       {"field": "call_number", "op": "contains", "value": "長福寺"},
       {"field": "call_number", "op": "contains", "value": "北野社"}
     ]},
    {"key": "social_sciences", "labels": {"ja": "社会科学"},
     "any": [
       {"field": "call_number", "op": "prefix", "value": "30"},
       {"field": "call_number", "op": "prefix", "value": "31"},
       {"field": "call_number", "op": "prefix", "value": "32"},
       {"field": "call_number", "op": "prefix", "value": "33"},
       {"field": "call_number", "op": "prefix", "value": "34"},
       {"field": "call_number", "op": "prefix", "value": "35"},
       {"field": "call_number", "op": "prefix", "value": "36"},
       {"field": "call_number", "op": "prefix", "value": "38"},
       {"field": "call_number", "op": "prefix", "value": "39"},
       {"field": "call_number", "op": "prefix", "value": "ム"},
       {"field": "call_number", "op": "prefix", "value": "ウ"},
       {"field": "call_number", "op": "prefix", "value": "オ"},
       {"field": "call_number", "op": "prefix", "value": "ヤ"},
       {"field": "call_number", "op": "prefix", "value": "ケ"},
       {"field": "call_number", "op": "prefix", "value": "キ"},
       {"field": "call_number", "op": "prefix", "value": "L"},
       {"field": "call_number", "op": "prefix", "value": "M"},
       {"field": "call_number", "op": "prefix", "value": "N"},
       {"field": "call_number", "op": "prefix", "value": "P"},
       {"field": "call_number", "op": "prefix", "value": "Q"},
       {"field": "call_number", "op": "prefix", "value": "V"}
     ]},
    {"key": "education", "labels": {"ja": "教育"},
     "any": [
       {"field": "call_number", "op": "prefix", "value": "37"},
       {"field": "call_number", "op": "prefix", "value": "ホ"},
       {"field": "call_number", "op": "prefix", "value": "ヘ"},
       {"field": "call_number", "op": "prefix", "value": "ル185"},
       {"field": "call_number", "op": "prefix", "value": "D"}
     ]},
    {"key": "natural_sciences", "labels": {"ja": "自然科学"},
     "any": [
       {"field": "call_number", "op": "prefix", "value": "4"},
       {"field": "call_number", "op": "prefix", "value": "コ"},
       {"field": "call_number", "op": "prefix", "value": "テ"},
       {"field": "call_number", "op": "prefix", "value": "サ"},
       {"field": "call_number", "op": "prefix", "value": "R"},
       {"field": "call_number", "op": "prefix", "value": "S"},
       {"field": "call_number", "op": "prefix", "value": "U"}
     ]},
    {"key": "technology", "labels": {"ja": "技術"},
     "any": [
       {"field": "call_number", "op": "prefix", "value": "5"},
       {"field": "call_number", "op": "prefix", "value": "ア"},
       {"field": "call_number", "op": "prefix", "value": "セ"},
       {"field": "call_number", "op": "prefix", "value": "T"},
       {"field": "call_number", "op": "prefix", "value": "Y"}
     ]},
    {"key": "industry", "labels": {"ja": "産業"},
     "any": [
       {"field": "call_number", "op": "prefix", "value": "6"},
       {"field": "call_number", "op": "prefix", "value": "ヒ"},
       {"field": "call_number", "op": "prefix", "value": "モ"},
       {"field": "call_number", "op": "prefix", "value": "W"},
       {"field": "call_number", "op": "prefix", "value": "X"}
     ]},
    {"key": "arts", "labels": {"ja": "芸術"},
     "any": [
       {"field": "call_number", "op": "prefix", "value": "7"},
       {"field": "call_number", "op": "prefix", "value": "カ"},
       {"field": "call_number", "op": "prefix", "value": "ス"},
       {"field": "call_number", "op": "prefix", "value": "G"},
       {"field": "call_number", "op": "prefix", "value": "Z"}
     ]},
    {"key": "language", "labels": {"ja": "言語"},
     "any": [
       {"field": "call_number", "op": "prefix", "value": "8"},
       {"field": "call_number", "op": "prefix", "value": "チ"},
       {"field": "call_number", "op": "prefix", "value": "E"}
     ]},
    {"key": "literature", "labels": {"ja": "文学"},
     "any": [
       {"field": "call_number", "op": "prefix", "value": "9"},
       {"field": "call_number", "op": "prefix", "value": "F"},
       {"all": [
         {"field": "call_number", "op": "prefix", "value": "ル"},
         {"field": "call_number", "op": "not_prefix", "value": "ル185"}
       ]}
     ]}
  ]
}';
  }

}
