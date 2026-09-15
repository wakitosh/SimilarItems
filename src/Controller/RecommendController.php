<?php

declare(strict_types=1);

namespace SimilarItems\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\JsonModel;
use SimilarItems\Experiment\ArmAssigner;
use SimilarItems\Log\LogService;
use SimilarItems\View\Helper\SimilarItems as SimilarItemsHelper;

/**
 * Serve SimilarItems recommendations asynchronously.
 */
class RecommendController extends AbstractActionController {

  /**
   * Usage log service.
   *
   * @var \SimilarItems\Log\LogService
   */
  private LogService $log;

  /**
   * Experiment arm assignment.
   *
   * @var \SimilarItems\Experiment\ArmAssigner
   */
  private ArmAssigner $arms;

  /**
   * Constructor.
   */
  public function __construct(LogService $log, ArmAssigner $arms) {
    $this->log = $log;
    $this->arms = $arms;
  }

  /**
   * Return recommendations HTML for a resource id.
   */
  public function listAction() {
    $services = $this->getEvent()->getApplication()->getServiceManager();
    $api = $services->get('Omeka\ApiManager');
    $view = $services->get('ViewRenderer');
    $vh = $services->get('ViewHelperManager');

    /** @var \SimilarItems\View\Helper\SimilarItems $similarHelper */
    $similarHelper = $vh->get(SimilarItemsHelper::class);

    $id = (int) $this->params()->fromQuery('id', 0);
    // Prefer explicit query param; otherwise fall back to route param when
    // the endpoint is site-scoped.
    $siteSlug = (string) $this->params()->fromQuery('site', '');
    if ($siteSlug === '') {
      $siteSlug = (string) $this->params()->fromRoute('site-slug', '');
    }
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
    // Optional A/B controls via query parameters.
    $tiebreak = (string) $this->params()->fromQuery('tiebreak', '');
    // Item set influence tuning for trials.
    $itemSetsWeightParam = $this->params()->fromQuery('item_sets_weight', NULL);
    $itemSetsSeedOnly = (int) $this->params()->fromQuery('item_sets_seed_only', 0) === 1;
    if ($id <= 0) {
      return new JsonModel(['html' => '']);
    }

    // Recommendations are personal to the moment of the request (jitter,
    // logging); never let a shared cache serve them.
    $this->getResponse()->getHeaders()->addHeaderLine('Cache-Control', 'no-store, private');

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

    // Experiment arm for this visitor. Assignment is per session and stable;
    // when the trial is switched off every visitor gets ARM_DEFAULT.
    $sessionKey = $this->log->isEnabled() ? $this->log->getSessionKey() : NULL;
    $arm = $this->arms->assign($sessionKey);

    $startedAt = microtime(TRUE);
    if ($arm === ArmAssigner::ARM_OFF) {
      // Control arm: nothing is shown. The impression is still recorded, so
      // that page views in this arm remain visible to session-level analysis.
      $results = [];
    }
    elseif ($arm === ArmAssigner::ARM_RANDOM) {
      // Control arm: same interface and item count, relevance removed.
      $results = $this->randomItems($api, $similarHelper, $item, $siteIdOpt, $limit);
    }
    else {
      try {
        $opts = ['limit' => $limit];
        if ($siteIdOpt) {
          $opts['site_id'] = (int) $siteIdOpt;
        }
        if ($tiebreak !== '') {
          // Accept: none|consensus|strength|identity (identity-first)
          $opts['tiebreak'] = (string) $tiebreak;
        }
        if ($itemSetsWeightParam !== NULL) {
          $opts['item_sets_weight'] = (int) $itemSetsWeightParam;
        }
        if ($itemSetsSeedOnly) {
          $opts['item_sets_seed_only'] = TRUE;
        }
        $results = (array) $similarHelper->__invoke($item, $opts);
      }
      catch (\Throwable $e) {
        $results = [];
      }
    }
    $durationMs = (int) round((microtime(TRUE) - $startedAt) * 1000);

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

    // Usage logging: record the impression and hand the browser the metadata
    // it needs to report views and clicks back.
    $logMeta = $this->recordImpression($item, $results, $siteSlug, [
      'limit' => $limit,
      'duration_ms' => $durationMs,
      // The control arms never run the scoring engine, so its statistics are
      // empty. The seed's domain buckets are still needed: without them the
      // cross-domain (serendipity) measure cannot be compared between arms,
      // which is one of the things the random arm exists to test.
      'stats' => ($arm === ArmAssigner::ARM_DEFAULT)
        ? $similarHelper->getLastStats()
        : ['seed_buckets' => $this->seedBuckets($similarHelper, $item)],
      'arm' => $arm,
      'tiebreak' => $tiebreak,
      'item_sets_weight' => $itemSetsWeightParam,
      'item_sets_seed_only' => $itemSetsSeedOnly,
    ]);
    if ($logMeta) {
      $html = $this->renderLogMeta($logMeta) . $html;
    }

    $payload = ['html' => (string) $html];
    if ($logMeta) {
      // Also expose it directly for themes that render the list themselves.
      $payload['log'] = $logMeta;
    }
    if ($debug) {
      // Include seed item details for debugging (id/title/base title/properties).
      try {
        $payload['debug_seed'] = $similarHelper->computeDebugSeedForResource($item);
      }
      catch (\Throwable $e) {
        $payload['debug_seed'] = NULL;
      }
      // Effective item set weight (respect trial override when present).
      $settings = $services->get('Omeka\Settings');
      $effItemSetsWeight = ($itemSetsWeightParam !== NULL) ? (int) $itemSetsWeightParam : (int) ($settings->get('similaritems.weight_item_sets') ?? 3);
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
          'values' => $row['debug_values'] ?? NULL,
        ];
      }
      $payload['debug'] = $debugOut;
      // Include request context for troubleshooting.
      $payload['debug_meta'] = [
        'site_param' => $siteSlug,
        'limit' => $limit,
        'tiebreak' => ($tiebreak !== '' ? $tiebreak : NULL),
        'item_sets_weight' => $effItemSetsWeight,
        'item_sets_seed_only' => $itemSetsSeedOnly ? 1 : 0,
        'duration_ms' => $durationMs,
      ];
    }

    return new JsonModel($payload);
  }

  /**
   * Write the impression row and build the payload sent to the browser.
   *
   * @param mixed $item
   *   Seed item representation.
   * @param array $results
   *   Ranked results as returned by the view helper.
   * @param string $siteSlug
   *   Current site slug, when known.
   * @param array $context
   *   Request context (limit, timing, helper statistics, A/B overrides).
   *
   * @return array|null
   *   Metadata for the client, or NULL when logging is disabled or failed.
   */
  private function recordImpression($item, array $results, string $siteSlug, array $context): ?array {
    if (!$this->log->isEnabled()) {
      return NULL;
    }
    $stats = is_array($context['stats'] ?? NULL) ? $context['stats'] : [];

    // Compact per-result record: enough to analyse rank, score and which
    // signals fired, without storing the whole representation.
    $stored = [];
    $client = [];
    $rank = 0;
    foreach ($results as $row) {
      $res = $row['resource'] ?? NULL;
      if (!$res || !method_exists($res, 'id')) {
        continue;
      }
      $rank++;
      $itemId = (int) $res->id();
      $stored[] = [
        'id' => $itemId,
        'rank' => $rank,
        'score' => round((float) ($row['score'] ?? 0), 3),
        'bucket' => (string) ($row['bucket'] ?? ''),
        'base_title' => (string) ($row['base_title'] ?? ''),
        'signals' => $this->summarizeSignals($row['signals'] ?? []),
      ];
      $client[] = [
        'id' => $itemId,
        'rank' => $rank,
        'url' => (string) ($row['link'] ?? ''),
      ];
    }

    $seedTitle = '';
    try {
      $seedTitle = (string) $item->displayTitle();
    }
    catch (\Throwable $e) {
      $seedTitle = '';
    }

    $written = $this->log->recordImpression([
      'seed_item_id' => (int) $item->id(),
      'seed_item_title' => $seedTitle,
      'seed_buckets' => implode(',', (array) ($stats['seed_buckets'] ?? [])),
      'seed_item_sets' => implode(',', (array) ($stats['seed_item_sets'] ?? [])),
      'site_slug' => $siteSlug,
      'locale' => $this->currentLocale(),
      'requested_limit' => (int) ($context['limit'] ?? 0),
      'candidate_count' => (int) ($stats['candidate_count'] ?? 0),
      'duration_ms' => (int) ($context['duration_ms'] ?? 0),
      'results' => $stored,
      'tiebreak' => (string) ($stats['tiebreak'] ?? ($context['tiebreak'] ?? '')),
      'jitter' => (int) ($stats['jitter'] ?? 0),
      'arm' => (string) ($context['arm'] ?? ArmAssigner::ARM_DEFAULT),
      'config_hash' => $this->log->computeConfigHash([
        'item_sets_weight' => $context['item_sets_weight'] ?? NULL,
        'item_sets_seed_only' => !empty($context['item_sets_seed_only']) ? 1 : 0,
        'tiebreak_override' => $context['tiebreak'] ?? '',
      ]),
      'user_id' => $this->currentUserId(),
    ]);
    if (!$written) {
      return NULL;
    }

    $meta = [
      'v' => 1,
      'impression' => $written['impression_key'],
      'endpoint' => $this->eventEndpoint($siteSlug),
      'seed' => (int) $item->id(),
      'site' => $siteSlug,
      'items' => $client,
    ];
    if (($context['arm'] ?? '') === ArmAssigner::ARM_OFF) {
      $meta['hide'] = 1;
    }
    return $meta;
  }

  /**
   * Domain buckets of the seed item, evaluated with the current rules.
   *
   * @return string[]
   *   Matched bucket keys, or an empty array.
   */
  private function seedBuckets($similarHelper, $item): array {
    try {
      return array_values($similarHelper->computeBucketsForResource($item));
    }
    catch (\Throwable $e) {
      return [];
    }
  }

  /**
   * Draw a random pool of items for the "random" control arm.
   *
   * Uses the same shape as the scoring helper's rows so that link building,
   * rendering and logging downstream need no special case. Buckets are filled
   * in from the same rules the scoring engine uses, which keeps the
   * cross-domain (serendipity) measure comparable between arms.
   *
   * @return array
   *   Result rows, or an empty array when nothing could be drawn.
   */
  private function randomItems($api, $similarHelper, $item, ?int $siteId, int $limit): array {
    try {
      $base = $siteId ? ['site_id' => (int) $siteId] : [];
      $countResp = $api->search('items', $base + ['limit' => 1]);
      $total = method_exists($countResp, 'getTotalResults') ? (int) $countResp->getTotalResults() : 0;
      $perPage = max(20, min(200, $limit * 4));
      $pages = max(1, (int) ceil(($total > 0 ? $total : $perPage) / $perPage));
      try {
        $page = random_int(1, $pages);
      }
      catch (\Throwable $e) {
        $page = 1;
      }
      $resp = $api->search('items', $base + ['page' => $page, 'per_page' => $perPage]);
      $pool = [];
      foreach ($resp->getContent() as $candidate) {
        if ((int) $candidate->id() === (int) $item->id()) {
          continue;
        }
        $bucket = '';
        try {
          $buckets = $similarHelper->computeBucketsForResource($candidate);
          $bucket = $buckets ? (string) reset($buckets) : '';
        }
        catch (\Throwable $e) {
          $bucket = '';
        }
        $pool[] = [
          'resource' => $candidate,
          'score' => 0.0,
          'bucket' => $bucket,
          'base_title' => '',
          'signals' => [['random_arm', 0]],
        ];
      }
      shuffle($pool);
      return array_slice($pool, 0, $limit);
    }
    catch (\Throwable $e) {
      return [];
    }
  }

  /**
   * Hidden JSON payload the module's client script picks up.
   *
   * It is prepended to the rendered list so that logging keeps working with
   * any theme override of the list partial.
   */
  private function renderLogMeta(array $meta): string {
    $json = json_encode(
      $meta,
      JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    );
    if ($json === FALSE) {
      return '';
    }
    return '<script type="application/json" data-similar-items-log>' . $json . '</script>';
  }

  /**
   * Collapse the helper's signal list into a name => total weight map.
   */
  private function summarizeSignals($signals): array {
    if (!is_array($signals)) {
      return [];
    }
    $out = [];
    foreach ($signals as $entry) {
      if (!is_array($entry) || !isset($entry[0])) {
        continue;
      }
      $name = (string) $entry[0];
      $weight = isset($entry[1]) ? (float) $entry[1] : 0.0;
      $out[$name] = round((float) ($out[$name] ?? 0) + $weight, 3);
    }
    return $out;
  }

  /**
   * Absolute path of the event collection endpoint for this site.
   */
  private function eventEndpoint(string $siteSlug): string {
    try {
      if ($siteSlug !== '') {
        return (string) $this->url()->fromRoute('similaritems-event-site', ['site-slug' => $siteSlug]);
      }
      return (string) $this->url()->fromRoute('similaritems-event');
    }
    catch (\Throwable $e) {
      return $siteSlug !== ''
        ? '/s/' . rawurlencode($siteSlug) . '/similar-items/event'
        : '/similar-items/event';
    }
  }

  /**
   * Current interface locale, when resolvable.
   */
  private function currentLocale(): ?string {
    try {
      $services = $this->getEvent()->getApplication()->getServiceManager();
      if ($services->has('MvcTranslator')) {
        $locale = (string) $services->get('MvcTranslator')->getLocale();
        return $locale !== '' ? $locale : NULL;
      }
    }
    catch (\Throwable $e) {
      // Ignore.
    }
    return NULL;
  }

  /**
   * Current user id, when signed in.
   */
  private function currentUserId(): ?int {
    try {
      $user = $this->identity();
      if ($user && method_exists($user, 'getId')) {
        return (int) $user->getId();
      }
    }
    catch (\Throwable $e) {
      // Anonymous visitor.
    }
    return NULL;
  }

}
