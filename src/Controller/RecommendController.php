<?php

declare(strict_types=1);

namespace SimilarItems\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\JsonModel;
use SimilarItems\View\Helper\SimilarItems as SimilarItemsHelper;

/**
 * Serve SimilarItems recommendations asynchronously.
 */
class RecommendController extends AbstractActionController {

  /**
   * Return recommendations HTML for a resource id.
   */
  public function listAction() {
    $services = $this->getEvent()->getApplication()->getServiceManager();
    $api = $services->get('Omeka\ApiManager');
    $view = $services->get('ViewRenderer');
    $vh = $services->get('ViewHelperManager');

    /** @var callable $similarHelper */
    $similarHelper = $vh->get(SimilarItemsHelper::class);

    $id = (int) $this->params()->fromQuery('id', 0);
    $siteSlug = (string) $this->params()->fromQuery('site', '');
    // Prefer explicit query param; otherwise fall back to module setting.
    $limitParam = $this->params()->fromQuery('limit', NULL);
    if ($limitParam !== NULL) {
      $limit = (int) $limitParam;
    }
    else {
      $settings = $services->get('Omeka\Settings');
      $limit = (int) ($settings->get('similaritems.limit') ?? 6);
    }
    if ($limit <= 0) {
      $limit = 6;
    }
    $debug = (int) $this->params()->fromQuery('debug', 0) === 1;
    if ($id <= 0) {
      return new JsonModel(['html' => '']);
    }

    try {
      $item = $api->read('items', $id)->getContent();
    }
    catch (\Throwable $e) {
      return new JsonModel(['html' => '']);
    }

    // Resolve site id from slug for correct scoping inside the helper.
    $siteIdOpt = NULL;
    if ($siteSlug !== '') {
      try {
        $resp = $api->search('sites', ['slug' => $siteSlug, 'limit' => 1]);
        $sites = $resp->getContent();
        if ($sites && isset($sites[0]) && method_exists($sites[0], 'id')) {
          $siteIdOpt = (int) $sites[0]->id();
        }
      }
      catch (\Throwable $e) {
        $siteIdOpt = NULL;
      }
    }

    try {
      $opts = ['limit' => $limit];
      if ($siteIdOpt) {
        $opts['site_id'] = (int) $siteIdOpt;
      }
      $results = (array) $similarHelper($item, $opts);
    }
    catch (\Throwable $e) {
      $results = [];
    }

    // Precompute public links for each result.
    // Prefer siteUrl (pretty URL) when possible.
    if (!empty($results)) {
      foreach ($results as &$row) {
        $res = $row['resource'] ?? NULL;
        $link = '';
        if ($res) {
          // 1) siteUrl when site slug is known (may be empty if not assigned)
          if ($siteSlug !== '' && method_exists($res, 'siteUrl')) {
            try {
              $link = (string) $res->siteUrl($siteSlug);
            }
            catch (\Throwable $e1) {
              $link = '';
            }
          }
          // 2) route: site/resource-id
          if ($link === '' && $siteSlug !== '' && method_exists($res, 'id')) {
            try {
              $link = (string) $this->url()->fromRoute('site/resource-id', [
                'site-slug' => $siteSlug,
                'controller' => 'item',
                'id' => (int) $res->id(),
              ]);
            }
            catch (\Throwable $e2) {
              $link = '';
            }
          }
          // 3) admin URL as last resort
          if ($link === '' && method_exists($res, 'url')) {
            try {
              $link = (string) $res->url();
            }
            catch (\Throwable $e3) {
              $link = '';
            }
          }
        }
        $row['link'] = $link;
      }
      unset($row);
    }

    $html = $view->partial('similar-items/partial/list', [
      'results' => $results,
      'siteSlug' => $siteSlug,
    ]);

    $payload = ['html' => (string) $html];
    if ($debug) {
      $debugOut = [];
      foreach ($results as $row) {
        $r = $row['resource'] ?? NULL;
        if (!$r) {
          continue;
        }
        $title = '';
        try {
          $title = (string) $r->displayTitle();
        }
        catch (\Throwable $e) {
          $title = '';
        }
        $url = '';
        try {
          $url = (string) ($siteSlug !== '' && method_exists($r, 'siteUrl') ? $r->siteUrl($siteSlug) : $r->url());
        }
        catch (\Throwable $e) {
          $url = '';
        }
        $debugOut[] = [
          'id' => (int) $r->id(),
          'title' => $title,
          'url' => $url,
          'score' => isset($row['score']) ? (float) $row['score'] : 0.0,
          'base_title' => isset($row['base_title']) ? (string) $row['base_title'] : '',
          'signals' => $row['signals'] ?? [],
        ];
      }
      $payload['debug'] = $debugOut;
      // Include request context for troubleshooting.
      $payload['debug_meta'] = [
        'site_param' => $siteSlug,
        'limit' => $limit,
      ];
    }

    return new JsonModel($payload);
  }

}
