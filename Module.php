<?php

declare(strict_types=1);

namespace SimilarItems;

use Omeka\Module\AbstractModule;
use Laminas\View\Renderer\PhpRenderer;
use Laminas\Mvc\Controller\AbstractController;
use SimilarItems\Form\ConfigForm;

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
      'similaritems_weight_item_sets' => (int) ($settings->get('similaritems.weight_item_sets') ?? 3),

      'similaritems_prop1_term' => (string) ($settings->get('similaritems.prop1.term') ?? ''),
      'similaritems_prop1_match' => (string) ($settings->get('similaritems.prop1.match') ?? 'eq'),
      'similaritems_prop1_weight' => (int) ($settings->get('similaritems.prop1.weight') ?? 2),

      'similaritems_prop2_term' => (string) ($settings->get('similaritems.prop2.term') ?? ''),
      'similaritems_prop2_match' => (string) ($settings->get('similaritems.prop2.match') ?? 'eq'),
      'similaritems_prop2_weight' => (int) ($settings->get('similaritems.prop2.weight') ?? 1),

      'similaritems_prop3_term' => (string) ($settings->get('similaritems.prop3.term') ?? ''),
      'similaritems_prop3_match' => (string) ($settings->get('similaritems.prop3.match') ?? 'eq'),
      'similaritems_prop3_weight' => (int) ($settings->get('similaritems.prop3.weight') ?? 1),

      'similaritems_prop4_term' => (string) ($settings->get('similaritems.prop4.term') ?? ''),
      'similaritems_prop4_match' => (string) ($settings->get('similaritems.prop4.match') ?? 'eq'),
      'similaritems_prop4_weight' => (int) ($settings->get('similaritems.prop4.weight') ?? 1),

      'similaritems_terms_per_property' => (int) ($settings->get('similaritems.terms_per_property') ?? 6),
      'similaritems_pool_multiplier' => (int) ($settings->get('similaritems.pool_multiplier') ?? 4),
    ]);

    $form->prepare();
    return $renderer->formCollection($form);
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
    $settings->set('similaritems.weight_item_sets', max(0, $getInt('similaritems_weight_item_sets', 3)));

    for ($i = 1; $i <= 4; $i++) {
      $settings->set("similaritems.prop{$i}.term", $getStr("similaritems_prop{$i}_term", ''));
      $match = $getStr("similaritems_prop{$i}_match", 'eq');
      if (!in_array($match, ['eq', 'cont', 'in'], TRUE)) {
        $match = 'eq';
      }
      $settings->set("similaritems.prop{$i}.match", $match);
      $settings->set("similaritems.prop{$i}.weight", max(0, $getInt("similaritems_prop{$i}_weight", 1)));
    }

    $settings->set('similaritems.terms_per_property', max(0, $getInt('similaritems_terms_per_property', 6)));
    $settings->set('similaritems.pool_multiplier', max(1, $getInt('similaritems_pool_multiplier', 4)));

    $controller->messenger()->addSuccess('SimilarItems settings were saved.');
    return TRUE;
  }

}
