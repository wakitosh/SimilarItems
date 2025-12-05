<?php

declare(strict_types=1);

namespace SimilarItems\View\Helper;

use Laminas\View\Helper\AbstractHelper;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Settings\Settings;

/**
 * SimilarItems view helper: compute similar items based on settings.
 */
class SimilarItems extends AbstractHelper {
  /**
   * Global settings service.
   *
   * @var \Omeka\Settings\Settings
   */
  private Settings $settings;

  /**
   * Logger (Omeka\Logger = Laminas\Log\Logger, or a PSR-3 logger). Optional.
   *
   * @var mixed
   */
  private $logger;

  /**
   * Constructor.
   */
  public function __construct(Settings $settings, $logger = NULL) {
    $this->settings = $settings;
    $this->logger = $logger;
  }

  /**
   * Compute similar items for the given resource.
   *
   * @param \Omeka\Api\Representation\AbstractResourceEntityRepresentation $resource
   *   Resource to compute neighbors for.
   * @param array $options
   *   Options such as ['limit' => int].
   *
   * @return array
   *   List of result rows with keys:
   *   - resource: representation
   *   - score: float
   *   - signals: array
   */
  public function __invoke(AbstractResourceEntityRepresentation $resource, array $options = []): array {
    /** @var \Laminas\View\Renderer\PhpRenderer $view */
    $view = $this->getView();
    $api = $view->api();

    // Debug can be enabled by setting.
    $debug = (int) ($this->settings->get('similaritems.debug_log') ?? 0) === 1;
    $log = function (string $message) use ($debug): void {
      if (!$debug) {
        return;
      }
      $prefix = '[SimilarItems] ';
      try {
        if ($this->logger) {
          $msg = $prefix . $message;
          // Prefer INFO for normal diagnostics.
          if (method_exists($this->logger, 'info')) {
            $this->logger->info($msg);
          }
          // Fall back to NOTICE.
          elseif (method_exists($this->logger, 'notice')) {
            $this->logger->notice($msg);
          }
          // Next: WARNING/warn if available.
          elseif (method_exists($this->logger, 'warning')) {
            $this->logger->warning($msg);
          }
          elseif (method_exists($this->logger, 'warn')) {
            $this->logger->warn($msg);
          }
          // Fallback to ->log(INFO, ...)
          elseif (method_exists($this->logger, 'log')) {
            $priority = (class_exists('Laminas\\Log\\Logger') && defined('Laminas\\Log\\Logger::INFO')) ? (int) constant('Laminas\\Log\\Logger::INFO') : 6;
            $this->logger->log($priority, $msg);
          }
          return;
        }
      }
      catch (\Throwable $e) {
        // Swallow logging errors.
      }
    };

    // Determine limit: option overrides module setting; fallback to 50.
    if (isset($options['limit'])) {
      $limit = (int) $options['limit'];
    }
    else {
      $limit = (int) ($this->settings->get('similaritems.limit') ?? 50);
      if ($limit <= 0) {
        $limit = 50;
      }
    }

    $scopeSite = (int) ($this->settings->get('similaritems.scope_site') ?? 1) === 1;
    $useItemSets = (int) ($this->settings->get('similaritems.use_item_sets') ?? 1) === 1;
    // Item set scoring weight (can be overridden per-request).
    $weightItemSets = (int) ($this->settings->get('similaritems.weight_item_sets') ?? 3);
    if (isset($options['item_sets_weight'])) {
      $weightItemSets = (int) $options['item_sets_weight'];
    }
    $itemSetsSeedOnly = (bool) ($options['item_sets_seed_only'] ?? FALSE);
    $applyItemSetsWeight = !$itemSetsSeedOnly && $weightItemSets > 0;

    // Mapping terms.
    $mapCall = (string) ($this->settings->get('similaritems.map.call_number') ?? '');
    $mapClass = (string) ($this->settings->get('similaritems.map.class_number') ?? '');
    $mapBib = (string) ($this->settings->get('similaritems.map.bibid') ?? '');
    $mapAuthorId = (string) ($this->settings->get('similaritems.map.author_id') ?? '');
    $mapAuthName = (string) ($this->settings->get('similaritems.map.authorized_name') ?? '');
    $mapSubject = (string) ($this->settings->get('similaritems.map.subject') ?? '');
    // Optional mapped properties for light boosts and debug visibility.
    $mapLocation = (string) ($this->settings->get('similaritems.map.location') ?? '');
    $mapIssued = (string) ($this->settings->get('similaritems.map.issued') ?? '');
    $mapMaterial = (string) ($this->settings->get('similaritems.map.material_type') ?? '');
    $mapViewing = (string) ($this->settings->get('similaritems.map.viewing_direction') ?? '');

    $mapSeries = (string) ($this->settings->get('similaritems.map.series_title') ?? '');
    $mapPublisher = (string) ($this->settings->get('similaritems.map.publisher') ?? '');

    // Weights.
    $wBib = (int) ($this->settings->get('similaritems.weight.bibid') ?? 0);
    $wAuthorId = (int) ($this->settings->get('similaritems.weight.author_id') ?? 6);
    $wAuthName = (int) ($this->settings->get('similaritems.weight.authorized_name') ?? 4);
    $wSubject = (int) ($this->settings->get('similaritems.weight.subject') ?? 4);
    $wBucket = (int) ($this->settings->get('similaritems.weight.domain_bucket') ?? 3);
    $wShelf = (int) ($this->settings->get('similaritems.weight.call_shelf') ?? 2);
    $wSeries = (int) ($this->settings->get('similaritems.weight.series_title') ?? 3);
    $wPublisher = (int) ($this->settings->get('similaritems.weight.publisher') ?? 2);
    $wClassProx = (int) ($this->settings->get('similaritems.weight.class_proximity') ?? 1);
    $wClassExact = (int) ($this->settings->get('similaritems.weight.class_exact') ?? 2);
    $classThresh = (int) ($this->settings->get('similaritems.class_proximity_threshold') ?? 5);
    // Optional small boosts.
    $wMaterial = (int) ($this->settings->get('similaritems.weight.material_type') ?? 2);
    $wIssuedProx = (int) ($this->settings->get('similaritems.weight.issued_proximity') ?? 1);
    $issuedThresh = (int) ($this->settings->get('similaritems.issued_proximity_threshold') ?? 5);
    $wPubPlace = (int) ($this->settings->get('similaritems.weight.publication_place') ?? 1);

    $multiMatchOn = ((int) ($this->settings->get('similaritems.multi_match.enable') ?? 0) === 1);
    $multiDecay = (float) (string) ($this->settings->get('similaritems.multi_match.decay') ?? '0.2');
    if (!is_finite($multiDecay) || $multiDecay < 0.0) {
      $multiDecay = 0.0;
    }
    // Optional: seed by shelf (call-number prefix) to broaden candidates.
    $useShelfSeeding = (int) ($this->settings->get('similaritems.use_shelf_seeding') ?? 0) === 1;
    $shelfSeedLimit = (int) ($this->settings->get('similaritems.shelf_seed_limit') ?? 50);
    $log('weights: bib=' . $wBib . ' author_id=' . $wAuthorId . ' auth_name=' . $wAuthName . ' subject=' . $wSubject . ' bucket=' . $wBucket . ' shelf=' . $wShelf . ' series=' . $wSeries . ' publisher=' . $wPublisher . ' class_exact=' . $wClassExact . ' class_prox=' . $wClassProx . ' class_thresh=' . $classThresh . ' material=' . $wMaterial . ' issued_prox=' . $wIssuedProx . ' issued_thresh=' . $issuedThresh . ' pub_place=' . $wPubPlace . ' multi_match=' . ($multiMatchOn ? '1' : '0') . ' multi_decay=' . $multiDecay);

    // Serendipity options: demote same-bibid (series/巻違い) aggressively.
    $demoteSameBib = (int) ($this->settings->get('similaritems.serendipity.demote_same_bibid') ?? 1) === 1;
    $sameBibPenalty = (int) ($this->settings->get('similaritems.serendipity.same_bibid_penalty') ?? 100);
    $sameTitleMode = (string) ($this->settings->get('similaritems.serendipity.same_title_mode') ?? 'allow');
    $excludeSameTitle = ($sameTitleMode === 'exclude');
    $log('serendipity: demote_same_bibid=' . ($demoteSameBib ? '1' : '0') . ' same_title_mode=' . $sameTitleMode);

    // Gather current item signals.
    $sig = [
      'bibid' => $mapBib ? $this->firstString($resource, $mapBib) : NULL,
      'author_id' => $mapAuthorId ? $this->firstString($resource, $mapAuthorId) : NULL,
      'auth_name' => $mapAuthName ? $this->firstString($resource, $mapAuthName) : NULL,
      'call' => $mapCall ? $this->firstString($resource, $mapCall) : NULL,
      'class' => $mapClass ? $this->firstString($resource, $mapClass) : NULL,
      'subject' => $mapSubject ? $this->firstStrings($resource, $mapSubject) : [],
      'location' => $mapLocation ? $this->firstString($resource, $mapLocation) : NULL,
      'issued' => $mapIssued ? $this->firstString($resource, $mapIssued) : NULL,
      'material' => $mapMaterial ? $this->firstString($resource, $mapMaterial) : NULL,
      'series' => $mapSeries ? $this->firstStrings($resource, $mapSeries) : [],
      'publisher' => $mapPublisher ? $this->firstStrings($resource, $mapPublisher) : [],
      'viewing' => $mapViewing ? $this->firstString($resource, $mapViewing) : NULL,
    ];

    $log('item ' . (int) $resource->id() . ' signals: ' . json_encode([
      'bibid' => $sig['bibid'],
      'author_id' => $sig['author_id'],
      'auth_name' => $sig['auth_name'],
      'call' => $sig['call'],
      'class' => $sig['class'],
      'subject_count' => is_array($sig['subject']) ? count($sig['subject']) : 0,
    ], JSON_UNESCAPED_UNICODE));

    $bucketRules = (string) ($this->settings->get('similaritems.bucket_rules') ?? '');
    $curBucketKeys = $this->evalBuckets($bucketRules, $sig['call'] ?? '', $sig['class'] ?? '');
    [$curShelf, $curClassNum] = $this->parseCallAndClass($sig['call'] ?? '', $sig['class'] ?? '');

    $siteId = NULL;
    // Allow explicit site id in options (e.g., from controller via site slug).
    if (isset($options['site_id'])) {
      $siteId = (int) $options['site_id'];
    }
    elseif ($scopeSite && method_exists($view, 'site') && $view->site()) {
      $siteId = $view->site()->id();
    }
    $log('scopeSite=' . ($scopeSite ? '1' : '0') . ' siteId=' . ($siteId ? (int) $siteId : 0));

    $candidates = [];
    $maxCandidates = 1000;

    // Resolve property term to numeric id when possible
    // (Omeka API prefers ids). Cache within this request.
    $propId = function (string $term) use ($api): string|int {
      static $cache = [];
      if ($term === '') {
        return '';
      }
      if (isset($cache[$term])) {
        return $cache[$term];
      }
      try {
        $resp = $api->search('properties', ['term' => $term, 'limit' => 1]);
        $props = $resp->getContent();
        if ($props && isset($props[0])) {
          /** @var \Omeka\Api\Representation\PropertyRepresentation $p */
          $p = $props[0];
          $cache[$term] = (int) $p->id();
          return $cache[$term];
        }
      }
      catch (\Throwable $e) {
        // Fall through to return term if property lookup fails.
      }
      $cache[$term] = $term;
      return $term;
    };

    // Seed candidates by shared Item Sets so the block works even when
    // bibliographic/authority properties are not mapped. Scoring for item
    // set overlap is added in post-processing below.
    $curItemSetIds = [];
    $curItemSets = [];
    if ($useItemSets && method_exists($resource, 'itemSets')) {
      foreach ($resource->itemSets() as $set) {
        $curItemSetIds[$set->id()] = TRUE;
        $curItemSets[$set->id()] = $set;
      }
    }
    if ($useItemSets && $curItemSetIds) {
      $log('seeding by item sets: ' . implode(',', array_keys($curItemSetIds)));
      foreach (array_keys($curItemSetIds) as $sid) {
        $query = [
          // Use array form for compatibility with Omeka API.
          'item_set_id' => [$sid],
          'limit' => 200,
        ];
        if ($siteId) {
          $query['site_id'] = $siteId;
        }
        $resp = $api->search('items', $query);
        $before = count($candidates);
        foreach ($resp->getContent() as $it) {
          if ($it->id() === $resource->id()) {
            continue;
          }
          $id = $it->id();
          if (!isset($candidates[$id])) {
            $candidates[$id] = ['resource' => $it, 'score' => 0.0, 'signals' => []];
          }
          if (count($candidates) >= $maxCandidates) {
            break 2;
          }
        }
        // If nothing added via API query (e.g., due to site filters), try
        // fetching via the Item Set representation directly.
        if ($before === count($candidates) && isset($curItemSets[$sid])) {
          try {
            $items = $curItemSets[$sid]->items(['limit' => 200]);
            foreach ($items as $it) {
              if ($it->id() === $resource->id()) {
                continue;
              }
              $id = $it->id();
              if (!isset($candidates[$id])) {
                $candidates[$id] = ['resource' => $it, 'score' => 0.0, 'signals' => []];
              }
              if (count($candidates) >= $maxCandidates) {
                break 2;
              }
            }
          }
          catch (\Throwable $e) {
            // Ignore; best-effort fallback.
          }
        }
      }
      $log('seeded candidates from item sets: ' . count($candidates));
    }

    // Fallback: if no candidates yet and we scoped to site, try again without
    // site filter so that something shows when items are not assigned to site.
    if ($useItemSets && $curItemSetIds && empty($candidates) && $siteId) {
      $log('fallback seeding by item sets without site filter');
      foreach (array_keys($curItemSetIds) as $sid) {
        $query = [
          'item_set_id' => [$sid],
          'limit' => 200,
        ];
        $resp = $api->search('items', $query);
        foreach ($resp->getContent() as $it) {
          if ($it->id() === $resource->id()) {
            continue;
          }
          $id = $it->id();
          if (!isset($candidates[$id])) {
            $candidates[$id] = ['resource' => $it, 'score' => 0.0, 'signals' => []];
          }
          if (count($candidates) >= $maxCandidates) {
            break 2;
          }
        }
      }
      $log('fallback seeded candidates from item sets: ' . count($candidates));
    }

    // Helper to add results from a property equality search.
    $addByProp = function (string $term, ?string $value, int $weight) use ($api, $siteId, $resource, &$candidates, $propId, $log, $maxCandidates) {
      if (!$term || $value === NULL || $value === '') {
        return;
      }
      $pid = $propId($term);
      $query = [
        'property' => [[
          'property' => $pid,
          'type' => 'eq',
          'text' => $value,
        ],
        ],
        'limit' => 200,
      ];
      if ($siteId) {
        $query['site_id'] = $siteId;
      }
      $log('query by prop eq: term=' . $term . ' pid=' . (is_int($pid) ? (string) $pid : $pid) . ' value=' . $value);
      $resp = $api->search('items', $query);
      $added = 0;
      foreach ($resp->getContent() as $it) {
        if ($it->id() === $resource->id()) {
          continue;
        }
        $id = $it->id();
        if (!isset($candidates[$id])) {
          $candidates[$id] = ['resource' => $it, 'score' => 0.0, 'signals' => []];
        }
        if (count($candidates) >= $maxCandidates) {
          break;
        }
        $candidates[$id]['score'] += $weight;
        $candidates[$id]['signals'][] = ["prop:$term", $weight];
        $added++;
      }
      $log('prop result added: ' . $added . ' (total candidates: ' . count($candidates) . ')');
    };

    if (!$demoteSameBib) {
      $addByProp($mapBib, $sig['bibid'], $wBib);
    }
    $addByProp($mapAuthorId, $sig['author_id'], $wAuthorId);
    $addByProp($mapAuthName, $sig['auth_name'], $wAuthName);
    if ($mapSubject && $wSubject > 0 && !empty($sig['subject'])) {
      foreach ($sig['subject'] as $sv) {
        $addByProp($mapSubject, $sv, $wSubject);
      }
    }

    if ($mapSeries && $wSeries !== 0 && !empty($sig['series'])) {
      foreach (array_unique($sig['series']) as $sv) {
        $addByProp($mapSeries, $sv, $wSeries);
      }
    }

    if ($mapPublisher && $wPublisher !== 0 && !empty($sig['publisher'])) {
      foreach (array_unique($sig['publisher']) as $sv) {
        $addByProp($mapPublisher, $sv, $wPublisher);
      }
    }

    // Optional seeding by shelf: add items whose call number starts with the
    // current shelf prefix (e.g., "210...") so that shelf weight can surface
    // more neighbors even when no other properties match.
    if ($useShelfSeeding && $wShelf > 0 && $mapCall && $curShelf !== '') {
      $pid = $propId($mapCall);
      $limitShelf = max(1, min(200, $shelfSeedLimit));
      $modes = [
        // Prefer starts-with when supported by the API.
        ['type' => 'sw', 'text' => $curShelf, 'label' => 'sw'],
        // Fallback to broad LIKE (contains) with user-provided wildcard.
        ['type' => 'like', 'text' => $curShelf . '%', 'label' => 'like'],
      ];
      foreach ($modes as $mode) {
        $query = [
          'property' => [
            [
              'property' => $pid,
              'type' => $mode['type'],
              'text' => $mode['text'],
            ],
          ],
          'limit' => $limitShelf,
        ];
        if ($siteId) {
          $query['site_id'] = $siteId;
        }
        $log('shelf seeding (' . $mode['label'] . '): shelf=' . $curShelf . ' pid=' . (is_int($pid) ? (string) $pid : $pid) . ' limit=' . $limitShelf);
        try {
          $resp = $api->search('items', $query);
          $scanned = 0;
          $added = 0;
          $exact = 0;
          $dups = 0;
          $mismatch = 0;
          $noCall = 0;
          $mismatchSamples = [];
          foreach ($resp->getContent() as $it) {
            if ($it->id() === $resource->id()) {
              continue;
            }
            $scanned++;
            // Filter to exact same shelf after normalization.
            $callVal = $mapCall ? ($this->firstString($it, $mapCall) ?? '') : '';
            $seedShelf = $this->parseShelf((string) $callVal);
            if ($seedShelf === '') {
              $noCall++;
              continue;
            }
            if ($seedShelf !== $curShelf) {
              $mismatch++;
              if (count($mismatchSamples) < 5) {
                $mismatchSamples[] = $callVal;
              }
              continue;
            }
            $exact++;
            $id = $it->id();
            if (!isset($candidates[$id])) {
              $candidates[$id] = ['resource' => $it, 'score' => 0.0, 'signals' => []];
              $added++;
            }
            else {
              $dups++;
            }
          }
          $msg = 'shelf seeding (' . $mode['label'] . ') scanned: ' . $scanned . ' exact: ' . $exact . ' dups: ' . $dups . ' added: ' . $added . ' mismatched: ' . $mismatch . ' no_call: ' . $noCall . ' (total candidates: ' . count($candidates) . ')';
          if ($mismatch > 0 && empty($exact) && !empty($mismatchSamples)) {
            $msg .= ' mismatch_samples=' . json_encode($mismatchSamples, JSON_UNESCAPED_UNICODE);
          }
          $log($msg);
          // If we found exact matches or added new ones, stop trying modes.
          if ($exact > 0 || $added > 0) {
            break;
          }
          // Otherwise, try next mode.
        }
        catch (\Throwable $e) {
          // Try next mode on errors.
          continue;
        }
      }
    }

    // Last-chance fallback: if still no candidates but we have item sets,
    // fetch a small batch directly from those sets (no site filter) so that
    // the block never comes up empty when item set overlap exists.
    if (empty($candidates) && $useItemSets && $curItemSetIds) {
      $log('last-chance fallback: union fetch from item sets');
      $resp = $api->search('items', [
        'item_set_id' => array_keys($curItemSetIds),
        'limit' => max(20, min(200, $limit * 4)),
      ]);
      foreach ($resp->getContent() as $it) {
        if ($it->id() === $resource->id()) {
          continue;
        }
        $id = $it->id();
        if (!isset($candidates[$id])) {
          $candidates[$id] = ['resource' => $it, 'score' => 0.0, 'signals' => []];
        }
        if ($applyItemSetsWeight) {
          $candidates[$id]['score'] += $weightItemSets;
          $candidates[$id]['signals'][] = ['item_sets', $weightItemSets];
        }
        if (count($candidates) >= $maxCandidates) {
          break;
        }
      }
      $log('last-chance fallback added: ' . count($candidates));
    }

    // Prepare serendipity penalty for near-duplicate titles (e.g., 巻違い).
    $baseTitle = '';
    try {
      $baseTitle = $this->normalizeTitleBase((string) $resource->displayTitle());
    }
    catch (\Throwable $e) {
      $baseTitle = '';
    }
    // Strong penalty to push out near duplicates.
    // NOTE: When "demote same bibid" is disabled, also disable
    // same-title penalty.
    // Use case: avoid offsetting a positive bibid weight by a strong
    // same-title penalty during tests.
    // Apply only when demote_same_bibid is enabled.
    $applySameTitlePenalty = $demoteSameBib;
    $sameTitlePenalty = $applySameTitlePenalty ? max(100, (int) $sameBibPenalty) : 0;
    $log('serendipity: same_title_penalty_applied=' . ($applySameTitlePenalty ? '1' : '0') . ' penalty=' . $sameTitlePenalty . ' same_bibid_penalty=' . (int) $sameBibPenalty);

    // Post-process candidates with bucket/shelf/class proximity and
    // item sets. Also compute candidate base titles for diversification.
    // Optional multi-match bonus helper (set-based, shared across signals).
    $bonusMultiMatch = function (array $seedVals, array $candVals, int $weight) use ($multiMatchOn, $multiDecay): float {
      if (!$multiMatchOn) {
        return 0.0;
      }
      if ($weight === 0) {
        return 0.0;
      }
      if (empty($seedVals) || empty($candVals)) {
        return 0.0;
      }
      $s = array_unique(array_filter(array_map('strval', $seedVals), static function ($v): bool {
        return $v !== '';
      }));
      $c = array_unique(array_filter(array_map('strval', $candVals), static function ($v): bool {
        return $v !== '';
      }));
      if (empty($s) || empty($c)) {
        return 0.0;
      }
      $n = count(array_intersect($s, $c));
      if ($n <= 0) {
        return 0.0;
      }
      if ($n === 1) {
        return (float) $weight;
      }
      $extra = ($n - 1) * ($weight * $multiDecay);
      return (float) $weight + (float) $extra;
    };

    foreach ($candidates as &$entry) {
      /** @var \Omeka\Api\Representation\AbstractResourceEntityRepresentation $it */
      $it = $entry['resource'];
      // Serendipity: penalize near-duplicate title base (when enabled).
      if ($baseTitle !== '') {
        try {
          $candTitle = $this->normalizeTitleBase((string) $it->displayTitle());
          $entry['base_title'] = $candTitle;
          if ($applySameTitlePenalty && $sameTitlePenalty > 0 && $candTitle !== '' && $candTitle === $baseTitle) {
            $entry['score'] -= $sameTitlePenalty;
            $entry['signals'][] = ['same_title_penalty', -$sameTitlePenalty];
          }
        }
        catch (\Throwable $e) {
          // Ignore.
        }
      }
      else {
        // Still compute base title for diversification even if current base
        // title could not be determined.
        try {
          $candTitle = $this->normalizeTitleBase((string) $it->displayTitle());
          $entry['base_title'] = $candTitle;
        }
        catch (\Throwable $e) {
          $entry['base_title'] = '';
        }
      }

      // Serendipity: demote same bibid when enabled.
      if ($demoteSameBib && $mapBib) {
        try {
          $candBib = (string) ($this->firstString($it, $mapBib) ?? '');
          if ($sig['bibid'] && $candBib !== '' && $candBib === $sig['bibid']) {
            $entry['score'] -= $sameBibPenalty;
            $entry['signals'][] = ['same_bibid_penalty', -$sameBibPenalty];
          }
        }
        catch (\Throwable $e) {
          // Ignore.
        }
      }
      // Ensure string types to satisfy strict signatures.
      $call = (string) ($mapCall ? ($this->firstString($it, $mapCall) ?? '') : '');
      $class = (string) ($mapClass ? ($this->firstString($it, $mapClass) ?? '') : '');
      $candBuckets = $this->evalBuckets($bucketRules, $call, $class);
      if ($wBucket > 0 && $curBucketKeys && $candBuckets) {
        if (count(array_intersect($curBucketKeys, $candBuckets)) > 0) {
          $entry['score'] += $wBucket;
          $entry['signals'][] = ['bucket', $wBucket];
        }
      }
      [$candShelf, $candClassNum] = $this->parseCallAndClass($call, $class);
      if ($wShelf > 0 && $curShelf && $candShelf && $curShelf === $candShelf) {
        $entry['score'] += $wShelf;
        $entry['signals'][] = ['shelf', $wShelf];
      }
      if ($wClassProx > 0 && $curClassNum !== NULL && $candClassNum !== NULL) {
        $diff = abs($curClassNum - $candClassNum);
        if ($diff <= $classThresh) {
          $entry['score'] += $wClassProx;
          $entry['signals'][] = ['class_proximity', $wClassProx];
        }
      }

      // Classification exact-match bonus (same normalized numeric part).
      if ($wClassExact !== 0 && $curClassNum !== NULL && $candClassNum !== NULL && $curClassNum === $candClassNum) {
        $entry['score'] += $wClassExact;
        $entry['signals'][] = ['class_exact', $wClassExact];
      }
      // Material type equality (light boost).
      if ($wMaterial > 0 && $mapMaterial) {
        try {
          $curMat = isset($sig['material']) ? (string) ($sig['material'] ?? '') : '';
          $candMat = (string) ($this->firstString($it, $mapMaterial) ?? '');
          if ($curMat !== '' && $candMat !== '') {
            // Case-insensitive compare after trimming.
            if (mb_strtolower(trim($curMat)) === mb_strtolower(trim($candMat))) {
              $entry['score'] += $wMaterial;
              $entry['signals'][] = ['material_type', $wMaterial];
            }
          }
        }
        catch (\Throwable $e) {
          // Ignore.
        }
      }
      // Publication place equality boost.
      if ($wPubPlace !== 0 && $mapLocation) {
        try {
          $curLoc = isset($sig['location']) ? (string) ($sig['location'] ?? '') : '';
          $candLoc = (string) ($this->firstString($it, $mapLocation) ?? '');
          if ($curLoc !== '' && $candLoc !== '') {
            $a = mb_strtolower(trim($curLoc));
            $b = mb_strtolower(trim($candLoc));
            if ($a === $b) {
              $entry['score'] += $wPubPlace;
              $entry['signals'][] = ['publication_place', $wPubPlace];
            }
          }
        }
        catch (hrowable $e) {
          // Ignore.
        }
      }
      // Issued proximity (within threshold years, light boost).
      if ($wIssuedProx > 0 && $mapIssued && $issuedThresh >= 0) {
        try {
          $curYear = $this->parseYear((string) ($sig['issued'] ?? ''));
          $candIssued = (string) ($this->firstString($it, $mapIssued) ?? '');
          $candYear = $this->parseYear($candIssued);
          if ($curYear !== NULL && $candYear !== NULL) {
            $diff = abs($curYear - $candYear);
            if ($diff <= $issuedThresh) {
              $entry['score'] += $wIssuedProx;
              $entry['signals'][] = ['issued_proximity', $wIssuedProx];
            }
          }
        }
        catch (\Throwable $e) {
          // Ignore.
        }
      }
      if ($useItemSets && $applyItemSetsWeight && $curItemSetIds) {
        $share = FALSE;
        if (method_exists($it, 'itemSets')) {
          foreach ($it->itemSets() as $set) {
            if (isset($curItemSetIds[$set->id()])) {
              $share = TRUE;
              break;
            }
          }
        }
        if ($share) {
          $entry['score'] += $weightItemSets;
          $entry['signals'][] = ['item_sets', $weightItemSets];
        }
      }

      // Multi-match bonuses for multi-valued signals (set-based).
      if ($multiMatchOn) {
        // Author IDs.
        if ($mapAuthorId && $wAuthorId !== 0 && $sig['author_id']) {
          $seedVals = [$sig['author_id']];
          $candVals = $this->firstStrings($it, $mapAuthorId);
          $delta = $bonusMultiMatch($seedVals, $candVals, $wAuthorId) - (float) $wAuthorId;
          if ($delta !== 0.0) {
            $entry['score'] += $delta;
            $entry['signals'][] = ['author_id_multi', $delta];
          }
        }
        // Authorized names.
        if ($mapAuthName && $wAuthName !== 0 && $sig['auth_name']) {
          $seedVals = [$sig['auth_name']];
          $candVals = $this->firstStrings($it, $mapAuthName);
          $delta = $bonusMultiMatch($seedVals, $candVals, $wAuthName) - (float) $wAuthName;
          if ($delta !== 0.0) {
            $entry['score'] += $delta;
            $entry['signals'][] = ['authorized_name_multi', $delta];
          }
        }
        // Subjects.
        if ($mapSubject && $wSubject !== 0 && !empty($sig['subject'])) {
          $seedVals = $sig['subject'];
          $candVals = $this->firstStrings($it, $mapSubject);
          $delta = $bonusMultiMatch($seedVals, $candVals, $wSubject) - (float) $wSubject;
          if ($delta !== 0.0) {
            $entry['score'] += $delta;
            $entry['signals'][] = ['subject_multi', $delta];
          }
        }
        // Series titles.
        if ($mapSeries && $wSeries !== 0 && !empty($sig['series'])) {
          $seedVals = $sig['series'];
          $candVals = $this->firstStrings($it, $mapSeries);
          $delta = $bonusMultiMatch($seedVals, $candVals, $wSeries) - (float) $wSeries;
          if ($delta !== 0.0) {
            $entry['score'] += $delta;
            $entry['signals'][] = ['series_multi', $delta];
          }
        }
        // Publishers (future-proof for multi-valued data).
        if ($mapPublisher && $wPublisher !== 0 && !empty($sig['publisher'])) {
          $seedVals = $sig['publisher'];
          $candVals = $this->firstStrings($it, $mapPublisher);
          $delta = $bonusMultiMatch($seedVals, $candVals, $wPublisher) - (float) $wPublisher;
          if ($delta !== 0.0) {
            $entry['score'] += $delta;
            $entry['signals'][] = ['publisher_multi', $delta];
          }
        }
      }

      // Debug values: include actual property values that relate to signals.
      // This is used only when the controller requests debug output.
      $propVals = [];
      try {
        if ($mapBib) {
          $vals = $this->firstStrings($it, $mapBib);
          if (!empty($vals)) {
            $propVals[$mapBib] = $vals;
          }
        }
        if ($mapAuthorId) {
          $vals = $this->firstStrings($it, $mapAuthorId);
          if (!empty($vals)) {
            $propVals[$mapAuthorId] = $vals;
          }
        }
        if ($mapAuthName) {
          $vals = $this->firstStrings($it, $mapAuthName);
          if (!empty($vals)) {
            $propVals[$mapAuthName] = $vals;
          }
        }
        if ($mapSubject) {
          $vals = $this->firstStrings($it, $mapSubject);
          if (!empty($vals)) {
            $propVals[$mapSubject] = $vals;
          }
        }
        if ($mapCall) {
          $vals = $this->firstStrings($it, $mapCall);
          if (!empty($vals)) {
            $propVals[$mapCall] = $vals;
          }
        }
        if ($mapClass) {
          $vals = $this->firstStrings($it, $mapClass);
          if (!empty($vals)) {
            $propVals[$mapClass] = $vals;
          }
        }
        if ($mapLocation) {
          $vals = $this->firstStrings($it, $mapLocation);
          if (!empty($vals)) {
            $propVals[$mapLocation] = $vals;
          }
        }
        if ($mapIssued) {
          $vals = $this->firstStrings($it, $mapIssued);
          if (!empty($vals)) {
            $propVals[$mapIssued] = $vals;
          }
        }
        if ($mapMaterial) {
          $vals = $this->firstStrings($it, $mapMaterial);
          if (!empty($vals)) {
            $propVals[$mapMaterial] = $vals;
          }
        }
        if ($mapViewing) {
          $vals = $this->firstStrings($it, $mapViewing);
          if (!empty($vals)) {
            $propVals[$mapViewing] = $vals;
          }
        }
      }
      catch (\Throwable $e) {
        // Ignore debug value collection errors.
      }
      $entry['debug_values'] = [
        'properties' => $propVals,
        'buckets' => $candBuckets,
        'shelf' => $candShelf,
        'class_number' => $candClassNum,
      ];
    }
    unset($entry);

    // Precompute tie-break metadata per candidate.
    // Includes: unique positive signal types, max positive component,
    // and tier presence flags.
    $canonicalOf = function (string $label) use ($mapAuthorId, $mapAuthName, $mapSubject): string {
      if (strpos($label, 'prop:') === 0) {
        $term = substr($label, 5);
        if ($term === $mapAuthorId) {
          return 'author_id';
        }
        if ($term === $mapAuthName) {
          return 'authorized_name';
        }
        if ($term === $mapSubject) {
          return 'subject';
        }
        return 'prop';
      }
      return $label;
    };
    foreach ($candidates as &$tb) {
      $uniq = [];
      $maxPos = 0.0;
      $t1 = 0;
      $t2 = 0;
      $t3 = 0;
      $t4 = 0;
      if (isset($tb['signals']) && is_array($tb['signals'])) {
        foreach ($tb['signals'] as $sigEntry) {
          $lab = isset($sigEntry[0]) ? (string) $sigEntry[0] : '';
          $w = isset($sigEntry[1]) ? (float) $sigEntry[1] : 0.0;
          if ($w <= 0.0) {
            continue;
          }
          $canon = $canonicalOf($lab);
          $uniq[$canon] = TRUE;
          if ($w > $maxPos) {
            $maxPos = $w;
          }
          // Tier flags.
          if ($canon === 'ncid' || $canon === 'author_id' || $canon === 'authorized_name') {
            $t1 = 1;
          }
          elseif ($canon === 'subject' || $canon === 'bucket') {
            $t2 = 1;
          }
          elseif ($canon === 'shelf' || $canon === 'class_proximity') {
            $t3 = 1;
          }
          elseif ($canon === 'material_type' || $canon === 'issued_proximity' || $canon === 'item_sets') {
            $t4 = 1;
          }
        }
      }
      $tb['__tb'] = [
        'uniq' => (int) count($uniq),
        'max' => (float) $maxPos,
        't1' => $t1,
        't2' => $t2,
        't3' => $t3,
        't4' => $t4,
      ];
    }
    unset($tb);

    // Sort by score desc. If jitter is enabled, break ties randomly; otherwise
    // fall back to modified desc for stability.
    $jitterOn = ((int) ($this->settings->get('similaritems.jitter.enable') ?? 0) === 1);
    if ($jitterOn) {
      foreach ($candidates as &$e) {
        $e['__rand'] = (mt_getrandmax() > 0) ? (mt_rand() / mt_getrandmax()) : (rand() / (getrandmax() ?: 1));
      }
      unset($e);
    }
    // Tie-break policy: none | consensus | strength | identity.
    $tiebreak = 'none';
    if (isset($options['tiebreak'])) {
      $tiebreak = strtolower((string) $options['tiebreak']);
    }
    else {
      $tiebreak = strtolower((string) ($this->settings->get('similaritems.tiebreak_policy') ?? 'none'));
    }
    if (!in_array($tiebreak, ['none', 'consensus', 'strength', 'identity'], TRUE)) {
      $tiebreak = 'none';
    }
    usort($candidates, function ($a, $b) use ($jitterOn, $tiebreak) {
      $sa = $a['score'] <=> $b['score'];
      if ($sa !== 0) {
        return -$sa;
      }
      // Apply tie-break policy on equal scores.
      if ($tiebreak !== 'none') {
        $ta = isset($a['__tb']) ? (array) $a['__tb'] : [];
        $tb = isset($b['__tb']) ? (array) $b['__tb'] : [];
        $ua = isset($ta['uniq']) ? (int) $ta['uniq'] : 0;
        $ub = isset($tb['uniq']) ? (int) $tb['uniq'] : 0;
        $ma = isset($ta['max']) ? (float) $ta['max'] : 0.0;
        $mb = isset($tb['max']) ? (float) $tb['max'] : 0.0;
        if ($tiebreak === 'consensus') {
          if ($ua !== $ub) {
            return ($ua > $ub) ? -1 : 1;
          }
          if ($ma !== $mb) {
            return ($ma > $mb) ? -1 : 1;
          }
        }
        elseif ($tiebreak === 'strength') {
          if ($ma !== $mb) {
            return ($ma > $mb) ? -1 : 1;
          }
          if ($ua !== $ub) {
            return ($ua > $ub) ? -1 : 1;
          }
        }
        elseif ($tiebreak === 'identity') {
          $t1a = isset($ta['t1']) ? (int) $ta['t1'] : 0;
          $t1b = isset($tb['t1']) ? (int) $tb['t1'] : 0;
          if ($t1a !== $t1b) {
            return ($t1a > $t1b) ? -1 : 1;
          }
          $t2a = isset($ta['t2']) ? (int) $ta['t2'] : 0;
          $t2b = isset($tb['t2']) ? (int) $tb['t2'] : 0;
          if ($t2a !== $t2b) {
            return ($t2a > $t2b) ? -1 : 1;
          }
          $t3a = isset($ta['t3']) ? (int) $ta['t3'] : 0;
          $t3b = isset($tb['t3']) ? (int) $tb['t3'] : 0;
          if ($t3a !== $t3b) {
            return ($t3a > $t3b) ? -1 : 1;
          }
          $t4a = isset($ta['t4']) ? (int) $ta['t4'] : 0;
          $t4b = isset($tb['t4']) ? (int) $tb['t4'] : 0;
          if ($t4a !== $t4b) {
            return ($t4a > $t4b) ? -1 : 1;
          }
          if ($ua !== $ub) {
            return ($ua > $ub) ? -1 : 1;
          }
          if ($ma !== $mb) {
            return ($ma > $mb) ? -1 : 1;
          }
        }
      }
      if ($jitterOn) {
        $ra = isset($a['__rand']) ? (float) $a['__rand'] : 0.0;
        $rb = isset($b['__rand']) ? (float) $b['__rand'] : 0.0;
        if ($ra !== $rb) {
          return ($ra > $rb) ? -1 : 1;
        }
      }
      $ma = $a['resource']->modified() ? $a['resource']->modified()->getTimestamp() : 0;
      $mb = $b['resource']->modified() ? $b['resource']->modified()->getTimestamp() : 0;
      if ($ma === $mb) {
        return 0;
      }
      return ($ma > $mb) ? -1 : 1;
    });

    // Final-stage diversification.
    // Allow: Keep score order (ties use jitter/modified date).
    // Exclude: Drop same-base-title items and promote diversity.
    $diversified = [];
    if (!$excludeSameTitle) {
      $diversified = $candidates;
    }
    else {
      $seenBases = [];
      $preferred = [];
      foreach ($candidates as $e) {
        $bt = isset($e['base_title']) ? (string) $e['base_title'] : '';
        if ($baseTitle !== '' && $bt !== '' && $bt === $baseTitle) {
          // In exclude mode, same-series entries are not taken.
          continue;
        }
        $preferred[] = $e;
      }
      // 1) まずは1ベースタイトル1件を優先してピック。
      foreach ($preferred as $e) {
        if ($limit > 0 && count($diversified) >= $limit) {
          break;
        }
        $bt = isset($e['base_title']) ? (string) $e['base_title'] : '';
        if ($bt !== '' && isset($seenBases[$bt])) {
          continue;
        }
        if ($bt !== '') {
          $seenBases[$bt] = TRUE;
        }
        $diversified[] = $e;
      }
      // 2) If still short, allow base-title duplicates to fill.
      if ($limit <= 0 || count($diversified) < $limit) {
        foreach ($preferred as $e) {
          if ($limit > 0 && count($diversified) >= $limit) {
            break;
          }
          $already = FALSE;
          foreach ($diversified as $d) {
            if ($d['resource']->id() === $e['resource']->id()) {
              $already = TRUE;
              break;
            }
          }
          if ($already) {
            continue;
          }
          $diversified[] = $e;
        }
      }
    }

    // Optional slight variability per reload.
    // Samples from a top pool when enabled.
    $jitterOn = ((int) ($this->settings->get('similaritems.jitter.enable') ?? 0) === 1);
    $poolMul = (float) (string) ($this->settings->get('similaritems.jitter.pool_multiplier') ?? '1.5');
    if (!is_finite($poolMul) || $poolMul < 1.0) {
      $poolMul = 1.0;
    }
    if ($jitterOn && $limit > 0 && count($diversified) > $limit) {
      $poolSize = (int) min(count($diversified), max($limit, (int) ceil($limit * $poolMul)));
      $pool = array_slice($diversified, 0, $poolSize);
      // Build non-negative weights from scores (shift so min=0, then +1).
      $minScore = 0.0;
      foreach ($pool as $e) {
        $s = isset($e['score']) ? (float) $e['score'] : 0.0;
        if ($s < $minScore) {
          $minScore = $s;
        }
      }
      $weights = [];
      foreach ($pool as $e) {
        $s = isset($e['score']) ? (float) $e['score'] : 0.0;
        // Ensure non-negative weight (shifted by min score) and at least 1.0.
        $w = ($s - $minScore) + 1.0;
        $weights[] = $w;
      }
      $picked = [];
      // Weighted sampling without replacement.
      for ($i = 0; $i < $limit && !empty($pool); $i++) {
        $sum = array_sum($weights);
        if ($sum <= 0) {
          // Fallback to simple shuffle if weights degenerate.
          shuffle($pool);
          $picked[] = array_shift($pool);
          $weights = array_slice($weights, 1);
          continue;
        }
        $r = (mt_rand() / (mt_getrandmax() ?: 1)) * $sum;
        $acc = 0.0;
        $chosen = 0;
        foreach ($weights as $idx => $w) {
          $acc += (float) $w;
          if ($acc >= $r) {
            $chosen = $idx;
            break;
          }
        }
        $picked[] = $pool[$chosen];
        array_splice($pool, $chosen, 1);
        array_splice($weights, $chosen, 1);
      }
      if (!empty($picked)) {
        $diversified = $picked;
        $log('jitter applied: pool=' . $poolSize . ' mult=' . $poolMul . ' out=' . count($diversified));
      }
    }
    else {
      if ($limit > 0 && count($diversified) > $limit) {
        $diversified = array_slice($diversified, 0, $limit);
      }
    }

    // If exclude mode removed all preferred candidates, fallback to random
    // items (site-scoped when applicable) so the UI can still show entries.
    if ($excludeSameTitle && $limit > 0 && count($diversified) === 0) {
      try {
        $base = [];
        if ($siteId) {
          $base['site_id'] = $siteId;
        }
        // Get total results to choose a random page.
        $countResp = $api->search('items', $base + ['limit' => 1]);
        $total = method_exists($countResp, 'getTotalResults') ? (int) $countResp->getTotalResults() : 0;
        $per = max(20, min(200, $limit * 3));
        $pages = ($per > 0) ? max(1, (int) ceil(($total > 0 ? $total : $per) / $per)) : 1;
        $page = 1;
        try {
          $page = random_int(1, $pages);
        }
        catch (\Throwable $e2) {
          $page = 1;
        }
        $log('random fallback query: total=' . $total . ' per=' . $per . ' page=' . $page . ($siteId ? (' site_id=' . (int) $siteId) : ''));
        $resp = $api->search('items', $base + ['page' => $page, 'per_page' => $per]);
        $pool = [];
        foreach ($resp->getContent() as $it) {
          if ($it->id() === $resource->id()) {
            continue;
          }
          $pool[] = [
            'resource' => $it,
            'score' => 0.0,
            'signals' => [['random_fallback', 0]],
          ];
        }
        // If site-scoped random produced nothing, retry without site filter.
        if (empty($pool) && $siteId) {
          $log('random fallback retry without site filter');
          $countResp2 = $api->search('items', ['limit' => 1]);
          $total2 = method_exists($countResp2, 'getTotalResults') ? (int) $countResp2->getTotalResults() : 0;
          $pages2 = ($per > 0) ? max(1, (int) ceil(($total2 > 0 ? $total2 : $per) / $per)) : 1;
          $page2 = 1;
          try {
            $page2 = random_int(1, $pages2);
          }
          catch (\Throwable $e3) {
            $page2 = 1;
          }
          $resp2 = $api->search('items', ['page' => $page2, 'per_page' => $per]);
          foreach ($resp2->getContent() as $it) {
            if ($it->id() === $resource->id()) {
              continue;
            }
            $pool[] = [
              'resource' => $it,
              'score' => 0.0,
              'signals' => [['random_fallback', 0]],
            ];
          }
        }
        if (!empty($pool)) {
          shuffle($pool);
          if (count($pool) > $limit) {
            $pool = array_slice($pool, 0, $limit);
          }
          $diversified = $pool;
        }
      }
      catch (\Throwable $e) {
        // Ignore fallback errors; leave empty.
      }
    }

    $log('final candidates: ' . count($diversified) . ' (limit=' . $limit . ')');

    return $diversified;
  }

  /**
   * Normalize a title to its base form to detect same-series variants.
   *
   * - Lowercases (for ASCII), trims whitespace.
   * - Removes common volume indicators (e.g., 第N巻, Vol. N, 巻N, (1), 1巻).
   * - Removes trailing parentheses/brackets with numbers.
   */
  private function normalizeTitleBase(string $title): string {
    $t = trim($title);
    if ($t === '') {
      return '';
    }
    // Normalize whitespace early to make separator matching robust.
    $t = preg_replace('/\s+/u', ' ', $t) ?? $t;
    // Apply configured title-volume separators first (cut right-hand part).
    try {
      $rawSeps = (string) ($this->settings->get('similaritems.title_volume_separators') ?? '');
      if ($rawSeps !== '') {
        $lines = preg_split('/\R+/', $rawSeps) ?: [];
        $seps = [];
        foreach ($lines as $ln) {
          $s = trim((string) $ln);
          if ($s !== '') {
            // Normalize whitespace inside separator to single space for
            // matching.
            $norm = preg_replace('/\s+/u', ' ', $s);
            $seps[] = $norm ?? $s;
          }
        }
        if (!empty($seps)) {
          foreach ($seps as $sep) {
            // Try several lenient variants: exact, trimmed, and around comma
            // without spaces.
            $cands = [];
            $trimSep = trim($sep);
            $cands[] = $sep;
            if ($trimSep !== $sep) {
              $cands[] = $trimSep;
            }
            // If separator contains a comma, also try Japanese comma
            // variants and spacing variants.
            if (mb_strpos($trimSep, ',') !== FALSE) {
              $noSpace = str_replace(' ', '', $trimSep);
              if ($noSpace !== $trimSep) {
                $cands[] = $noSpace;
              }
              // Fullwidth comma.
              $cands[] = str_replace(',', '，', $trimSep);
              // Japanese toten.
              $cands[] = str_replace(',', '、', $trimSep);
              // Also without spaces for these.
              $cands[] = str_replace(' ', '', str_replace(',', '，', $trimSep));
              $cands[] = str_replace(' ', '', str_replace(',', '、', $trimSep));
            }
            $cut = FALSE;
            foreach ($cands as $cand) {
              $pos = mb_stripos($t, $cand);
              if ($pos !== FALSE && $pos > 0) {
                $t = mb_substr($t, 0, $pos);
                $cut = TRUE;
                break;
              }
            }
            if ($cut) {
              break;
            }
          }
        }
      }
    }
    catch (\Throwable $e) {
      // Ignore setting errors; continue with normalization.
    }
    // Normalize spaces and ASCII case for rough match (again, post-split).
    $t = preg_replace('/\s+/u', ' ', $t) ?? $t;
    $t = mb_strtolower($t);
    // Remove common volume markers.
    $patterns = [
      // Arabic numerals.
      '/\s*第\s*\d+\s*巻\s*$/u',
      '/\s*\d+\s*巻\s*$/u',
      '/\s*巻\s*\d+\s*$/u',
      '/\s*vol\.?\s*\d+\s*$/iu',
      '/\s*volume\s*\d+\s*$/iu',
      '/\s*\(\s*\d+\s*\)\s*$/u',
      '/\s*\[\s*\d+\s*\]\s*$/u',
      '/\s*\-\s*\d+\s*$/u',
      '/\s*\d+\s*$/u',
      // Kanji numerals.
      '/\s*第\s*[一二三四五六七八九十百零〇]+\s*巻\s*$/u',
      '/\s*[一二三四五六七八九十百零〇]+\s*巻\s*$/u',
      // Japanese variants like 上ノ上, 上ノ下, 中ノ上, 中ノ下, 下ノ上, 下ノ中, 下ノ下.
      '/[\s,、，]*上ノ上\s*$/u',
      '/[\s,、，]*上ノ下\s*$/u',
      '/[\s,、，]*中ノ上\s*$/u',
      '/[\s,、，]*中ノ下\s*$/u',
      '/[\s,、，]*下ノ上\s*$/u',
      '/[\s,、，]*下ノ中\s*$/u',
      '/[\s,、，]*下ノ下\s*$/u',
      // Same variants with 之 instead of ノ.
      '/[\s,、，]*上之上\s*$/u',
      '/[\s,、，]*上之下\s*$/u',
      '/[\s,、，]*中之上\s*$/u',
      '/[\s,、，]*中之下\s*$/u',
      '/[\s,、，]*下之上\s*$/u',
      '/[\s,、，]*下之中\s*$/u',
      '/[\s,、，]*下之下\s*$/u',
    ];
    $t = preg_replace($patterns, '', $t) ?? $t;
    return trim($t);
  }

  /**
   * Get first string value for a term.
   */
  private function firstString(AbstractResourceEntityRepresentation $res, string $term): ?string {
    if (!$term) {
      return NULL;
    }
    $val = $res->value($term);
    if (!$val) {
      return NULL;
    }
    $str = (string) $val;
    $str = trim($str);
    return $str !== '' ? $str : NULL;
  }

  /**
   * Get all string values for a term.
   *
   * @return string[]
   *   Array of string values.
   */
  private function firstStrings(AbstractResourceEntityRepresentation $res, string $term): array {
    if (!$term) {
      return [];
    }
    $vals = $res->value($term, ['all' => TRUE]);
    if (!$vals) {
      return [];
    }
    $out = [];
    foreach ($vals as $v) {
      $s = trim((string) $v);
      if ($s !== '') {
        $out[] = $s;
      }
    }
    return $out;
  }

  /**
   * Evaluate bucket rules and return matched bucket keys.
   *
   * Supports ops: prefix, contains, equals and their not_* variants,
   * and nested all/any.
   *
   * @return string[]
   *   Matched bucket keys.
   */
  private function evalBuckets(string $jsonRules, string $call, string $class): array {
    if ($jsonRules === '') {
      return [];
    }
    $data = json_decode($jsonRules, TRUE);
    if (!is_array($data) || !isset($data['buckets']) || !is_array($data['buckets'])) {
      return [];
    }
    // Build context. If class is empty, derive numeric class from call
    // (e.g., "210-H..." -> "210").
    $classNorm = (string) $class;
    if ($classNorm === '' && $call !== '') {
      $num = $this->parseClassNumber($call);
      if ($num !== NULL) {
        $classNorm = (string) $num;
      }
    }
    $ctx = [
      'call_number' => (string) $call,
      'class_number' => $classNorm,
    ];
    $matched = [];
    foreach ($data['buckets'] as $bucket) {
      if (!is_array($bucket)) {
        continue;
      }
      $ok = FALSE;
      if (isset($bucket['any']) && is_array($bucket['any'])) {
        foreach ($bucket['any'] as $cond) {
          if ($this->evalCond($cond, $ctx)) {
            $ok = TRUE;
            break;
          }
        }
      }
      if (!$ok && isset($bucket['all']) && is_array($bucket['all'])) {
        $ok = TRUE;
        foreach ($bucket['all'] as $cond) {
          if (!$this->evalCond($cond, $ctx)) {
            $ok = FALSE;
            break;
          }
        }
      }
      if ($ok && isset($bucket['key'])) {
        $matched[] = (string) $bucket['key'];
      }
    }
    return $matched;
  }

  /**
   * Evaluate a single condition on the given context map.
   */
  private function evalCond($cond, array $ctx): bool {
    if (!is_array($cond)) {
      return FALSE;
    }
    $field = isset($cond['field']) ? (string) $cond['field'] : '';
    $op = isset($cond['op']) ? (string) $cond['op'] : '';
    $value = isset($cond['value']) ? (string) $cond['value'] : '';
    if ($field === '' || $op === '') {
      return FALSE;
    }
    $target = isset($ctx[$field]) ? (string) $ctx[$field] : '';
    $targetLower = mb_strtolower($target);
    $valueLower = mb_strtolower($value);
    $neg = FALSE;
    if (strpos($op, 'not_') === 0) {
      $neg = TRUE;
      $op = substr($op, 4);
    }
    $res = FALSE;
    switch ($op) {
      case 'prefix':
        $res = ($valueLower !== '' && strpos($targetLower, $valueLower) === 0);
        break;

      case 'contains':
        $res = ($valueLower !== '' && mb_strpos($targetLower, $valueLower) !== FALSE);
        break;

      case 'equals':
        $res = ($targetLower === $valueLower);
        break;

      default:
        $res = FALSE;
    }
    return $neg ? !$res : $res;
  }

  /**
   * Parse shelf string and class number from call/class values.
   *
   * @return array
   *   [string shelf, int|null classNumber]
   */
  private function parseCallAndClass(string $call, string $class): array {
    $shelf = $this->parseShelf($call);
    // Prefer explicit class when present; if it's non-numeric, fall back to
    // deriving from call number.
    $classNum = $this->parseClassNumber($class !== '' ? $class : $call);
    if ($classNum === NULL && $class !== '') {
      $classNum = $this->parseClassNumber($call);
    }
    return [$shelf, $classNum];
  }

  /**
   * Extract shelf code from a call number (e.g., leading letters like QA, TK).
   */
  private function parseShelf(string $call): string {
    if ($call === '') {
      return '';
    }
    // Normalize common Unicode separators to ASCII for robust prefix parsing.
    $s = (string) $call;
    // 1) Convert fullwidth alphanumerics and spaces to ASCII when available.
    // This fixes cases like "２１０-H12" vs "210-H12".
    try {
      if (function_exists('mb_convert_kana')) {
        // 'a' => alnum to ASCII, 's' => spaces to ASCII.
        $s = mb_convert_kana($s, 'as', 'UTF-8');
      }
    }
    catch (\Throwable $e) {
      // Ignore; fallback to manual normalization below.
    }
    // Full-width space -> space.
    $s = preg_replace('/\x{3000}/u', ' ', $s) ?? $s;
    // Various Unicode hyphens and minus signs -> '-'.
    $s = preg_replace('/[\x{2010}\x{2011}\x{2012}\x{2013}\x{2014}\x{2212}\x{FF0D}]/u', '-', $s) ?? $s;
    // Full-width dot -> '.'.
    $s = preg_replace('/\x{FF0E}/u', '.', $s) ?? $s;
    // Japanese middle dot -> space (acts as delimiter).
    $s = preg_replace('/\x{30FB}/u', ' ', $s) ?? $s;
    $s = trim($s);
    if ($s === '') {
      return '';
    }
    // If starts with digits, return that digit block
    // (e.g., 210 from 210-..., 121.52-...).
    if (preg_match('/^\d+/', $s, $m)) {
      return strtoupper($m[0]);
    }
    // If starts with 1–3 ASCII letters (e.g., QA76), return that.
    if (preg_match('/^[A-Za-z]{1,3}/', $s, $m)) {
      return strtoupper($m[0]);
    }
    // Fallback: take until first space, dot, or hyphen
    // (Unicode-normalized above).
    if (preg_match('/^[^\s\.\-]+/u', $s, $m)) {
      return strtoupper($m[0]);
    }
    return '';
  }

  /**
   * Extract a primary class number from a string.
   */
  private function parseClassNumber(string $s): ?int {
    if ($s === '') {
      return NULL;
    }
    // Normalize fullwidth alphanumerics to ASCII for digit extraction.
    try {
      if (function_exists('mb_convert_kana')) {
        $s = mb_convert_kana($s, 'as', 'UTF-8');
      }
    }
    catch (\Throwable $e) {
      // Ignore and continue.
    }
    if (preg_match('/\d+/', $s, $m)) {
      return (int) $m[0];
    }
    return NULL;
  }

  /**
   * Extract a 4-digit year from an issued/date string.
   */
  private function parseYear(string $s): ?int {
    if ($s === '') {
      return NULL;
    }
    // Normalize fullwidth alphanumerics/spaces to ASCII for digits.
    try {
      if (function_exists('mb_convert_kana')) {
        $s = mb_convert_kana($s, 'as', 'UTF-8');
      }
    }
    catch (\Throwable $e) {
      // Ignore.
    }
    // Look for 4-digit year in a reasonable range (e.g., 1000–2999).
    if (preg_match('/\b(1\d{3}|2\d{3})\b/', $s, $m)) {
      $y = (int) $m[1];
      return $y;
    }
    // Fallback: first 3-4 digit sequence.
    if (preg_match('/(\d{3,4})/', $s, $m)) {
      $y = (int) $m[1];
      return $y;
    }
    return NULL;
  }

  /**
   * Public utility: compute bucket keys for a resource using current settings.
   *
   * @param \Omeka\Api\Representation\AbstractResourceEntityRepresentation $res
   *   Resource to evaluate.
   *
   * @return string[]
   *   Matched bucket keys.
   */
  public function computeBucketsForResource(AbstractResourceEntityRepresentation $res): array {
    $mapCall = (string) ($this->settings->get('similaritems.map.call_number') ?? '');
    $mapClass = (string) ($this->settings->get('similaritems.map.class_number') ?? '');
    $bucketRules = (string) ($this->settings->get('similaritems.bucket_rules') ?? '');
    $call = $mapCall ? ($this->firstString($res, $mapCall) ?? '') : '';
    $class = $mapClass ? ($this->firstString($res, $mapClass) ?? '') : '';
    return $this->evalBuckets($bucketRules, (string) $call, (string) $class);
  }

}
