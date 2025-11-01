<?php

declare(strict_types=1);

namespace SimilarItems;

use Omeka\Module\AbstractModule;
use Laminas\View\Renderer\PhpRenderer;
use Laminas\Mvc\Controller\AbstractController;
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
    $services = $this->getServiceLocator();
    $settings = $services->get('Omeka\Settings');
    $form = $services->get('FormElementManager')->get(ConfigForm::class);

    // Populate defaults from settings.
    $form->setData([
      'similaritems_scope_site' => (int) ($settings->get('similaritems.scope_site') ?? 1),
      'similaritems_use_item_sets' => (int) ($settings->get('similaritems.use_item_sets') ?? 1),
      'similaritems_limit' => (int) ($settings->get('similaritems.limit') ?? 6),
      'similaritems_weight_item_sets' => (int) ($settings->get('similaritems.weight_item_sets') ?? 2),
      'similaritems_debug_log' => (int) ($settings->get('similaritems.debug_log') ?? 0),
      // Shelf seeding.
      'similaritems_use_shelf_seeding' => (int) ($settings->get('similaritems.use_shelf_seeding') ?? 0),
      'similaritems_shelf_seed_limit' => (int) ($settings->get('similaritems.shelf_seed_limit') ?? 50),
      // Jitter.
      'similaritems_jitter_enable' => (int) ($settings->get('similaritems.jitter.enable') ?? 0),
      'similaritems_jitter_pool_multiplier' => (string) ($settings->get('similaritems.jitter.pool_multiplier') ?? '1.5'),

      // Property mapping (advanced)
      'similaritems_map_call_number' => (string) ($settings->get('similaritems.map.call_number') ?? 'dcndl:callNumber'),
      'similaritems_map_class_number' => (string) ($settings->get('similaritems.map.class_number') ?? 'dc:subject'),
      'similaritems_map_bibid' => (string) ($settings->get('similaritems.map.bibid') ?? 'tsukubada:bibid'),
      'similaritems_map_ncid' => (string) ($settings->get('similaritems.map.ncid') ?? 'dcndl:sourceIdentifier'),
      'similaritems_map_author_id' => (string) ($settings->get('similaritems.map.author_id') ?? 'tsukubada:authorId'),
      'similaritems_map_authorized_name' => (string) ($settings->get('similaritems.map.authorized_name') ?? 'tsukubada:authorizedName'),
      'similaritems_map_location' => (string) ($settings->get('similaritems.map.location') ?? 'dcndl:location'),
      'similaritems_map_issued' => (string) ($settings->get('similaritems.map.issued') ?? 'dcterms:issued'),
      'similaritems_map_material_type' => (string) ($settings->get('similaritems.map.material_type') ?? 'dcndl:materialType'),
      'similaritems_map_viewing_direction' => (string) ($settings->get('similaritems.map.viewing_direction') ?? 'tsukubada:viewingDirection'),
      'similaritems_map_subject' => (string) ($settings->get('similaritems.map.subject') ?? ''),

      // Weights (basic set).
      'similaritems_weight_bibid' => (int) ($settings->get('similaritems.weight.bibid') ?? 0),
      'similaritems_weight_ncid' => (int) ($settings->get('similaritems.weight.ncid') ?? 6),
      'similaritems_weight_author_id' => (int) ($settings->get('similaritems.weight.author_id') ?? 5),
      'similaritems_weight_authorized_name' => (int) ($settings->get('similaritems.weight.authorized_name') ?? 3),
      'similaritems_weight_subject' => (int) ($settings->get('similaritems.weight.subject') ?? 5),
      'similaritems_weight_domain_bucket' => (int) ($settings->get('similaritems.weight.domain_bucket') ?? 2),
      'similaritems_weight_call_shelf' => (int) ($settings->get('similaritems.weight.call_shelf') ?? 1),
      'similaritems_weight_class_proximity' => (int) ($settings->get('similaritems.weight.class_proximity') ?? 1),
      'similaritems_weight_material_type' => (int) ($settings->get('similaritems.weight.material_type') ?? 2),
      'similaritems_weight_issued_proximity' => (int) ($settings->get('similaritems.weight.issued_proximity') ?? 1),
      'similaritems_issued_proximity_threshold' => (int) ($settings->get('similaritems.issued_proximity_threshold') ?? 5),
      'similaritems_class_proximity_threshold' => (int) ($settings->get('similaritems.class_proximity_threshold') ?? 5),

      // Domain bucket rules JSON.
      'similaritems_bucket_rules' => (string) ($settings->get('similaritems.bucket_rules') ?? $this->getDefaultBucketRules()),

      // Serendipity options.
      'similaritems_serendipity_demote_same_bibid' => (int) ($settings->get('similaritems.serendipity.demote_same_bibid') ?? 1),
      'similaritems_same_bibid_penalty' => (int) ($settings->get('similaritems.serendipity.same_bibid_penalty') ?? 150),
      'similaritems_serendipity_same_title_mode' => (string) ($settings->get('similaritems.serendipity.same_title_mode') ?? 'allow'),

      // Title rules.
      'similaritems_title_volume_separators' => (string) ($settings->get('similaritems.title_volume_separators') ?? ' , '),
    ]);

    $form->prepare();
    return $renderer->formCollection($form);
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
      'similaritems.weight_item_sets' => 2,
      'similaritems.debug_log' => 0,
      // Shelf seeding defaults.
      'similaritems.use_shelf_seeding' => 0,
      'similaritems.shelf_seed_limit' => 50,

      // Property mapping defaults.
      'similaritems.map.call_number' => 'dcndl:callNumber',
      'similaritems.map.class_number' => 'dc:subject',
      'similaritems.map.bibid' => 'tsukubada:bibid',
      'similaritems.map.ncid' => 'dcndl:sourceIdentifier',
      'similaritems.map.author_id' => 'tsukubada:authorId',
      'similaritems.map.authorized_name' => 'tsukubada:authorizedName',
      'similaritems.map.location' => 'dcndl:location',
      'similaritems.map.issued' => 'dcterms:issued',
      'similaritems.map.material_type' => 'dcndl:materialType',
      'similaritems.map.viewing_direction' => 'tsukubada:viewingDirection',
      'similaritems.map.subject' => '',

      // Weights (basic set) defaults.
      'similaritems.weight.bibid' => 0,
      'similaritems.weight.ncid' => 6,
      'similaritems.weight.author_id' => 5,
      'similaritems.weight.authorized_name' => 3,
      'similaritems.weight.subject' => 5,
      'similaritems.weight.domain_bucket' => 2,
      'similaritems.weight.call_shelf' => 1,
      'similaritems.weight.class_proximity' => 1,
      'similaritems.weight.material_type' => 2,
      'similaritems.weight.issued_proximity' => 1,
      'similaritems.class_proximity_threshold' => 5,
      'similaritems.issued_proximity_threshold' => 5,

      // Domain bucket rules JSON default.
      'similaritems.bucket_rules' => $this->getDefaultBucketRules(),

      // Serendipity defaults.
      'similaritems.serendipity.demote_same_bibid' => 1,
      'similaritems.serendipity.same_bibid_penalty' => 150,
      'similaritems.serendipity.same_title_mode' => 'allow',

      // Title rules defaults.
      'similaritems.title_volume_separators' => ' , ',
      'similaritems.title_max_length' => 60,
      // Jitter defaults.
      'similaritems.jitter.enable' => 0,
      'similaritems.jitter.pool_multiplier' => '1.5',
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
    $settings->set('similaritems.weight_item_sets', max(0, $getInt('similaritems_weight_item_sets', 3)));
    $settings->set('similaritems.debug_log', $getInt('similaritems_debug_log', 0));
    // Shelf seeding.
    $settings->set('similaritems.use_shelf_seeding', $getInt('similaritems_use_shelf_seeding', 0));
    $settings->set('similaritems.shelf_seed_limit', max(0, $getInt('similaritems_shelf_seed_limit', 50)));
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
    $settings->set('similaritems.map.ncid', $getStr('similaritems_map_ncid', ''));
    $settings->set('similaritems.map.author_id', $getStr('similaritems_map_author_id', ''));
    $settings->set('similaritems.map.authorized_name', $getStr('similaritems_map_authorized_name', ''));
    $settings->set('similaritems.map.location', $getStr('similaritems_map_location', ''));
    $settings->set('similaritems.map.issued', $getStr('similaritems_map_issued', ''));
    $settings->set('similaritems.map.material_type', $getStr('similaritems_map_material_type', ''));
    $settings->set('similaritems.map.viewing_direction', $getStr('similaritems_map_viewing_direction', ''));
    $settings->set('similaritems.map.subject', $getStr('similaritems_map_subject', ''));

    // Save weights (basic set).
    $settings->set('similaritems.weight.bibid', max(0, $getInt('similaritems_weight_bibid', 10)));
    $settings->set('similaritems.weight.ncid', max(0, $getInt('similaritems_weight_ncid', 8)));
    $settings->set('similaritems.weight.author_id', max(0, $getInt('similaritems_weight_author_id', 6)));
    $settings->set('similaritems.weight.authorized_name', max(0, $getInt('similaritems_weight_authorized_name', 4)));
    $settings->set('similaritems.weight.domain_bucket', max(0, $getInt('similaritems_weight_domain_bucket', 3)));
    $settings->set('similaritems.weight.call_shelf', max(0, $getInt('similaritems_weight_call_shelf', 2)));
    $settings->set('similaritems.weight.class_proximity', max(0, $getInt('similaritems_weight_class_proximity', 1)));
    $settings->set('similaritems.class_proximity_threshold', max(0, $getInt('similaritems_class_proximity_threshold', 5)));
    $settings->set('similaritems.weight.subject', max(0, $getInt('similaritems_weight_subject', 4)));
    $settings->set('similaritems.weight.material_type', max(0, $getInt('similaritems_weight_material_type', 2)));
    $settings->set('similaritems.weight.issued_proximity', max(0, $getInt('similaritems_weight_issued_proximity', 1)));
    $settings->set('similaritems.issued_proximity_threshold', max(0, $getInt('similaritems_issued_proximity_threshold', 5)));

    // Save bucket rules JSON (no validation here; UI can include a test tool).
    $settings->set('similaritems.bucket_rules', $getStr('similaritems_bucket_rules', ''));

    // Serendipity options.
    $settings->set('similaritems.serendipity.demote_same_bibid', $getInt('similaritems_serendipity_demote_same_bibid', 1));
    $settings->set('similaritems.serendipity.same_bibid_penalty', max(0, $getInt('similaritems_same_bibid_penalty', 100)));
    $settings->set('similaritems.serendipity.same_title_mode', $getStr('similaritems_serendipity_same_title_mode', 'allow'));

    // Title rules.
    $settings->set('similaritems.title_volume_separators', $getStr('similaritems_title_volume_separators', ' , '));

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
    {"key": "documents", "labels": {"ja": "文書類"},
     "any": [
       {"field": "call_number", "op": "prefix", "value": "2"},
       {"field": "call_number", "op": "prefix", "value": "ヨ"},
       {"field": "call_number", "op": "prefix", "value": "タ"},
       {"field": "call_number", "op": "prefix", "value": "ネ"},
       {"field": "call_number", "op": "prefix", "value": "H"},
       {"field": "call_number", "op": "prefix", "value": "J"},
       {"field": "call_number", "op": "prefix", "value": "K"},
       {"field": "call_number", "op": "contains", "value": "雑文書"},
       {"field": "call_number", "op": "contains", "value": "昌平坂"},
       {"field": "call_number", "op": "contains", "value": "石清水"},
       {"field": "call_number", "op": "contains", "value": "大徳寺"},
       {"field": "call_number", "op": "contains", "value": "長福寺"},
       {"field": "call_number", "op": "contains", "value": "北野社"},
       {"field": "call_number", "op": "contains", "value": "東亜研"}
     ]},
    {"key": "maps", "labels": {"ja": "地図"},
     "any": [
       {"field": "call_number", "op": "prefix", "value": "2"},
       {"field": "call_number", "op": "prefix", "value": "ヨ"},
       {"field": "call_number", "op": "prefix", "value": "タ"},
       {"field": "call_number", "op": "prefix", "value": "ネ"},
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
       {"field": "class_number", "op": "prefix", "value": "30"},
       {"field": "class_number", "op": "prefix", "value": "31"},
       {"field": "class_number", "op": "prefix", "value": "32"},
       {"field": "class_number", "op": "prefix", "value": "33"},
       {"field": "class_number", "op": "prefix", "value": "34"},
       {"field": "class_number", "op": "prefix", "value": "35"},
       {"field": "class_number", "op": "prefix", "value": "36"},
       {"field": "class_number", "op": "prefix", "value": "38"},
       {"field": "class_number", "op": "prefix", "value": "39"},
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
       {"field": "class_number", "op": "prefix", "value": "37"},
       {"field": "call_number", "op": "prefix", "value": "ホ"},
       {"field": "call_number", "op": "prefix", "value": "ヘ"},
       {"field": "call_number", "op": "prefix", "value": "ル185"},
       {"field": "call_number", "op": "prefix", "value": "D"}
     ]},
    {"key": "textbook", "labels": {"ja": "教科書"},
     "any": [
       {"field": "class_number", "op": "prefix", "value": "37"},
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
