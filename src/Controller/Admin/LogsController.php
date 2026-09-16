<?php

/**
 * @file
 * Admin UI for browsing, summarising and exporting SimilarItems usage logs.
 */

declare(strict_types=1);

namespace SimilarItems\Controller\Admin;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Omeka\Form\ConfirmForm;
use SimilarItems\Experiment\ArmAssigner;
use SimilarItems\Log\LogService;

/**
 * Browse and export the recommendation usage logs.
 */
class LogsController extends AbstractActionController {

  /**
   * Ranks reported in the rank/CTR breakdown.
   */
  private const MAX_RANK = 12;

  /**
   * Usage log service.
   *
   * @var \SimilarItems\Log\LogService
   */
  private LogService $log;

  /**
   * Doctrine DBAL connection.
   *
   * @var \Doctrine\DBAL\Connection|null
   */
  private $conn;

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
    $this->conn = $log->getConnection();
  }

  /**
   * Aggregated dashboard: exposure, response, browsing chains, serendipity.
   */
  public function indexAction() {
    $this->log->ensureTables();
    // Apply the retention policy when an administrator looks at the logs; this
    // avoids needing a cron job for a modest amount of data.
    $purged = $this->log->purgeExpired();
    if ($purged > 0) {
      $this->messenger()->addNotice(
        sprintf($this->translate('Retention policy removed %d expired impression log(s).'), $purged)
      );
    }
    $filters = $this->getFilters();
    $vm = new ViewModel([
      'filters' => $filters,
      'summary' => $this->buildSummary($filters),
      'enabled' => $this->log->isEnabled(),
      // Needed to reproduce the stored IP hashes when joining this log to a
      // web server access log. Shown only on this admin-only page.
      'salt' => $this->log->ensureSalt(),
      'arms' => $this->buildArmComparison($filters),
      // What the current configuration actually allocates, which is not always
      // what the weight fields suggest once an arm is switched off.
      'allocation' => $this->arms->getAllocation(),
      'confirmForm' => $this->getForm(ConfirmForm::class),
    ]);
    $vm->setTemplate('similar-items/logs/index');
    return $vm;
  }

  /**
   * How to read the dashboard.
   *
   * Static, so it stays readable when there is no data yet and can be sent to
   * someone who does not have access to the admin interface.
   */
  public function helpAction() {
    $vm = new ViewModel();
    $vm->setTemplate('similar-items/logs/help');
    return $vm;
  }

  /**
   * Paginated impression list.
   */
  public function impressionsAction() {
    return $this->renderList('impressions');
  }

  /**
   * Paginated event list.
   */
  public function eventsAction() {
    return $this->renderList('events');
  }

  /**
   * Export a dataset as CSV or TSV.
   */
  public function exportAction() {
    $this->log->ensureTables();
    $dataset = (string) $this->params()->fromQuery('dataset', 'impressions');
    if (!in_array($dataset, ['impressions', 'events', 'chains'], TRUE)) {
      $dataset = 'impressions';
    }
    $format = strtolower((string) $this->params()->fromQuery('format', 'csv'));
    $sep = ($format === 'tsv') ? "\t" : ',';
    $filters = $this->getFilters();
    $tz = $this->getTimeZone();

    $fmtIso = function ($epoch) use ($tz) {
      $t = (int) $epoch;
      if ($t <= 0) {
        return '';
      }
      try {
        $dt = new \DateTime('@' . $t);
        $dt->setTimezone(new \DateTimeZone($tz));
        return $dt->format('Y-m-d\TH:i:sP');
      }
      catch (\Throwable $e) {
        return '';
      }
    };

    [$rows, $cols] = $this->fetchDataset($dataset, $filters);

    try {
      $now = new \DateTime('now', new \DateTimeZone($tz));
      $stamp = $now->format('Y-m-d\TH:i:sP');
    }
    catch (\Throwable $e) {
      $stamp = date('Y-m-d\TH:i:sP');
    }
    $ext = ($format === 'tsv') ? 'tsv' : 'csv';
    $base = 'similaritems_' . $dataset . '_' . $stamp;
    $filenameSafe = str_replace(':', '-', $base) . '.' . $ext;

    header('Content-Type: text/' . (($format === 'tsv') ? 'tab-separated-values' : 'csv') . '; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filenameSafe . '"; filename*=UTF-8\'\'' . rawurlencode($base . '.' . $ext));
    $out = fopen('php://output', 'w');
    if ($format !== 'tsv') {
      // BOM so Excel detects UTF-8.
      fwrite($out, "\xEF\xBB\xBF");
    }
    fputcsv($out, $cols, $sep);
    $timeCols = ['created_at', 'started_at', 'ended_at'];
    foreach ($rows as $r) {
      $line = [];
      foreach ($cols as $c) {
        $val = $r[$c] ?? '';
        if (in_array($c, $timeCols, TRUE)) {
          $val = $fmtIso($val);
        }
        $line[] = is_scalar($val) || $val === NULL ? (string) $val : json_encode($val);
      }
      fputcsv($out, $line, $sep);
    }
    fclose($out);
    exit;
  }

  /**
   * Delete the impressions selected on the impression list, with their events.
   *
   * Deleting an impression that other impressions were chained to would leave
   * them pointing at a row that no longer exists, so the affected chains are
   * rebuilt afterwards rather than left dangling.
   */
  public function deleteAction() {
    $this->log->ensureTables();
    $request = $this->getRequest();
    $isPost = method_exists($request, 'isPost')
      ? $request->isPost()
      : (strtoupper((string) $request->getMethod()) === 'POST');
    if (!$isPost) {
      return $this->redirectToImpressions();
    }
    $form = $this->getForm(ConfirmForm::class);
    $form->setData($this->params()->fromPost());
    if (!$form->isValid()) {
      $this->messenger()->addError($this->translate('Security token is invalid.'));
      return $this->redirectToImpressions();
    }

    $ids = $this->params()->fromPost('impression_ids', []);
    $ids = is_array($ids) ? array_values(array_unique(array_filter(array_map('intval', $ids)))) : [];
    if (!$ids) {
      $this->messenger()->addWarning($this->translate('No impression was selected.'));
      return $this->redirectToImpressions();
    }

    try {
      $placeholders = implode(',', array_fill(0, count($ids), '?'));
      $rows = $this->conn->fetchAllAssociative(
        'SELECT id, impression_key, chain_key FROM ' . LogService::TABLE_IMPRESSION
        . ' WHERE id IN (' . $placeholders . ')',
        $ids
      );
      if (!$rows) {
        $this->messenger()->addWarning($this->translate('The selected impressions no longer exist.'));
        return $this->redirectToImpressions();
      }
      $keys = array_column($rows, 'impression_key');
      $chains = array_values(array_unique(array_filter(array_column($rows, 'chain_key'))));

      $keyPlaceholders = implode(',', array_fill(0, count($keys), '?'));
      $events = (int) $this->conn->executeStatement(
        'DELETE FROM ' . LogService::TABLE_EVENT . ' WHERE impression_key IN (' . $keyPlaceholders . ')',
        $keys
      );
      $deleted = (int) $this->conn->executeStatement(
        'DELETE FROM ' . LogService::TABLE_IMPRESSION . ' WHERE id IN (' . $placeholders . ')',
        $ids
      );
      $repaired = $this->rebuildChains($chains);

      $message = sprintf(
        $this->translate('Deleted %1$d impression(s) and %2$d event(s).'),
        $deleted,
        $events
      );
      if ($repaired > 0) {
        $message .= ' ' . sprintf(
          $this->translate('%d impression(s) that followed a deleted one were detached and their chains recomputed.'),
          $repaired
        );
      }
      $this->messenger()->addSuccess($message);
    }
    catch (\Throwable $e) {
      $this->messenger()->addError($this->translate('Failed to delete: ') . $e->getMessage());
    }
    return $this->redirectToImpressions();
  }

  /**
   * Recompute chain position for the impressions left in the given chains.
   *
   * An impression whose parent is gone becomes a chain root again; the ones
   * below it keep their links but have their depth and chain key recomputed.
   * Impressions are always created after their parent, so processing them in
   * id order means a parent is resolved before its children.
   *
   * @param string[] $chainKeys
   *   Chains touched by the deletion.
   *
   * @return int
   *   Number of impressions detached from a deleted parent.
   */
  private function rebuildChains(array $chainKeys): int {
    if (!$chainKeys) {
      return 0;
    }
    $detached = 0;
    $placeholders = implode(',', array_fill(0, count($chainKeys), '?'));
    $rows = $this->conn->fetchAllAssociative(
      'SELECT id, impression_key, parent_event_id, parent_impression_id, chain_key, hop_depth, entry_kind '
      . 'FROM ' . LogService::TABLE_IMPRESSION . ' WHERE chain_key IN (' . $placeholders . ') ORDER BY id ASC',
      $chainKeys
    );
    // Resolved position of every impression seen so far in these chains.
    $resolved = [];
    foreach ($rows as $row) {
      $id = (int) $row['id'];
      $parentId = $row['parent_impression_id'] !== NULL ? (int) $row['parent_impression_id'] : 0;
      $parent = $parentId && isset($resolved[$parentId]) ? $resolved[$parentId] : NULL;
      if (!$parent && $parentId) {
        // The parent is outside these chains; it may still exist.
        $exists = (int) $this->conn->fetchOne(
          'SELECT COUNT(*) FROM ' . LogService::TABLE_IMPRESSION . ' WHERE id = ?',
          [$parentId]
        );
        if ($exists) {
          $parentRow = $this->conn->fetchAssociative(
            'SELECT chain_key, hop_depth FROM ' . LogService::TABLE_IMPRESSION . ' WHERE id = ?',
            [$parentId]
          );
          $parent = [
            'chain_key' => (string) $parentRow['chain_key'],
            'hop_depth' => (int) $parentRow['hop_depth'],
          ];
        }
      }

      if ($parent) {
        $chainKey = $parent['chain_key'];
        $hopDepth = $parent['hop_depth'] + 1;
        $update = [];
      }
      else {
        // Root again: the link that explained how the visitor got here is gone.
        $chainKey = (string) $row['impression_key'];
        $hopDepth = 0;
        $update = [
          'parent_event_id' => NULL,
          'parent_impression_id' => NULL,
        ];
        if ($parentId) {
          $detached++;
          if ($row['entry_kind'] === 'similar_items') {
            $update['entry_kind'] = NULL;
          }
        }
      }

      if ($update
        || (string) $row['chain_key'] !== $chainKey
        || (int) $row['hop_depth'] !== $hopDepth) {
        $update['chain_key'] = $chainKey;
        $update['hop_depth'] = $hopDepth;
        $this->conn->update(LogService::TABLE_IMPRESSION, $update, ['id' => $id]);
      }
      $resolved[$id] = ['chain_key' => $chainKey, 'hop_depth' => $hopDepth];
    }
    return $detached;
  }

  /**
   * Return to the impression list, keeping the current filters and page.
   */
  private function redirectToImpressions() {
    $query = array_filter(
      $this->params()->fromPost('return', []),
      function ($v) {
        return $v !== '' && $v !== NULL;
      }
    );
    return $this->redirect()->toRoute('admin/similar-items-logs-impressions', [], ['query' => $query]);
  }

  /**
   * Delete logs, either everything up to now or within a date range.
   */
  public function clearAction() {
    $this->log->ensureTables();
    $request = $this->getRequest();
    $isPost = method_exists($request, 'isPost')
      ? $request->isPost()
      : (strtoupper((string) $request->getMethod()) === 'POST');
    if (!$isPost) {
      return $this->redirect()->toRoute('admin/similar-items-logs');
    }
    $form = $this->getForm(ConfirmForm::class);
    $post = $this->params()->fromPost();
    $form->setData($post);
    if (!$form->isValid()) {
      $this->messenger()->addError($this->translate('Security token is invalid.'));
      return $this->redirect()->toRoute('admin/similar-items-logs');
    }

    $mode = trim((string) ($post['mode'] ?? 'now'));
    $startTs = NULL;
    $endTs = NULL;
    if ($mode === 'range') {
      $startTs = $this->parseDateTimeLocal((string) ($post['after_datetime'] ?? ''));
      $endTs = $this->parseDateTimeLocal((string) ($post['before_datetime_range'] ?? ''));
      if ($startTs === NULL || $endTs === NULL || $startTs > $endTs) {
        $this->messenger()->addError($this->translate('Please specify a valid date/time range.'));
        return $this->redirect()->toRoute('admin/similar-items-logs');
      }
    }
    elseif ($mode === 'before') {
      $endTs = $this->parseDateTimeLocal((string) ($post['before_datetime'] ?? ''));
      if ($endTs === NULL) {
        $this->messenger()->addError($this->translate('Please specify a valid date and time.'));
        return $this->redirect()->toRoute('admin/similar-items-logs');
      }
    }
    else {
      $endTs = time();
    }

    try {
      $params = [];
      $where = '';
      if ($startTs !== NULL) {
        $where = 'created_at >= ? AND created_at <= ?';
        $params = [$startTs, $endTs];
      }
      else {
        $where = 'created_at <= ?';
        $params = [$endTs];
      }
      // Events first: they reference impressions.
      $this->conn->executeStatement('DELETE FROM ' . LogService::TABLE_EVENT . ' WHERE ' . $where, $params);
      $deleted = (int) $this->conn->executeStatement(
        'DELETE FROM ' . LogService::TABLE_IMPRESSION . ' WHERE ' . $where,
        $params
      );
      $this->messenger()->addSuccess(sprintf($this->translate('Deleted %d impression log(s).'), $deleted));
    }
    catch (\Throwable $e) {
      $this->messenger()->addError($this->translate('Failed to delete logs: ') . $e->getMessage());
    }
    return $this->redirect()->toRoute('admin/similar-items-logs');
  }

  /**
   * Render a paginated table, as a full page or as an AJAX partial.
   */
  private function renderList(string $dataset) {
    $this->log->ensureTables();
    $filters = $this->getFilters();
    $page = max(1, (int) $this->params()->fromQuery('page', 1));
    $perPage = min(200, max(10, (int) $this->params()->fromQuery('per_page', 50)));
    $offset = ($page - 1) * $perPage;

    $table = $dataset === 'events' ? LogService::TABLE_EVENT : LogService::TABLE_IMPRESSION;
    [$where, $params] = $this->buildWhere($filters, $dataset);

    $rows = [];
    $total = 0;
    try {
      $rows = $this->conn->fetchAllAssociative(
        'SELECT * FROM ' . $table . ' ' . $where . ' ORDER BY id DESC LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset,
        $params
      );
      $total = (int) $this->conn->fetchOne('SELECT COUNT(*) FROM ' . $table . ' ' . $where, $params);
    }
    catch (\Throwable $e) {
      $this->messenger()->addError($this->translate('Failed to read logs: ') . $e->getMessage());
    }

    $vm = new ViewModel([
      'dataset' => $dataset,
      'rows' => $rows,
      'page' => $page,
      'perPage' => $perPage,
      'total' => $total,
      'filters' => $filters,
      'confirmForm' => $this->getForm(ConfirmForm::class),
    ]);
    $request = $this->getRequest();
    $isAjax = (int) $this->params()->fromQuery('ajax', 0) === 1
      || (method_exists($request, 'isXmlHttpRequest') && $request->isXmlHttpRequest());
    $vm->setTemplate('similar-items/logs/partial/' . $dataset);
    if ($isAjax) {
      $vm->setTerminal(TRUE);
      return $vm;
    }
    $layout = new ViewModel([
      'dataset' => $dataset,
      'filters' => $filters,
    ]);
    $layout->addChild($vm, 'table');
    $layout->setTemplate('similar-items/logs/list');
    return $layout;
  }

  /**
   * Fetch rows and column names for an export dataset.
   */
  private function fetchDataset(string $dataset, array $filters): array {
    if ($dataset === 'chains') {
      [$where, $params] = $this->buildWhere($filters, 'impressions');
      $sql = 'SELECT chain_key, MIN(session_key) AS session_key, MIN(created_at) AS started_at, '
        . 'MAX(created_at) AS ended_at, COUNT(*) AS impressions, MAX(hop_depth) AS max_hop, '
        . 'MIN(variant) AS variant, MIN(arm) AS arm, MIN(entry_kind) AS entry_kind, MIN(device) AS device, '
        . 'GROUP_CONCAT(seed_item_id ORDER BY hop_depth ASC, created_at ASC SEPARATOR ";") AS item_path '
        . 'FROM ' . LogService::TABLE_IMPRESSION . ' ' . $where
        . ' GROUP BY chain_key ORDER BY started_at DESC';
      $cols = [
        'chain_key', 'session_key', 'started_at', 'ended_at', 'impressions',
        'max_hop', 'variant', 'arm', 'entry_kind', 'device', 'item_path',
      ];
      return [$this->safeFetchAll($sql, $params), $cols];
    }

    $isEvents = ($dataset === 'events');
    $table = $isEvents ? LogService::TABLE_EVENT : LogService::TABLE_IMPRESSION;
    [$where, $params] = $this->buildWhere($filters, $dataset);
    $rows = $this->safeFetchAll('SELECT * FROM ' . $table . ' ' . $where . ' ORDER BY id ASC', $params);
    $cols = $isEvents
      ? [
        'id', 'created_at', 'event_key', 'event_type', 'impression_id', 'impression_key',
        'session_key', 'visitor_key', 'user_id', 'site_slug', 'variant', 'arm', 'seed_item_id',
        'target_item_id', 'target_rank', 'target_score', 'target_signals', 'target_bucket',
        'cross_domain', 'dwell_ms', 'visible_ms', 'was_visible', 'device', 'is_bot', 'client_ip',
      ]
      : [
        'id', 'created_at', 'impression_key', 'session_key', 'visitor_key', 'user_id',
        'site_slug', 'locale', 'seed_item_id', 'seed_item_title', 'seed_buckets',
        'seed_item_sets', 'requested_limit', 'result_count', 'candidate_count',
        'duration_ms', 'is_empty', 'variant', 'arm', 'config_hash', 'tiebreak', 'jitter',
        'parent_event_id', 'parent_impression_id', 'chain_key', 'hop_depth', 'entry_kind',
        'referrer_host', 'device', 'is_bot', 'client_ip', 'results',
      ];
    return [$rows, $cols];
  }

  /**
   * Compare the experiment arms.
   *
   * Reported per arm so the two control contrasts are readable directly:
   * "default vs off" shows what the feature contributes to browsing, and
   * "default vs random" shows what the scoring contributes on top of simply
   * putting items on the page. Pages and items per session are the outcomes
   * that exist in every arm, including the one where nothing is displayed.
   *
   * @return array
   *   Arm name => figures. Empty when no arm has been recorded.
   */
  private function buildArmComparison(array $filters): array {
    if (!$this->conn) {
      return [];
    }
    [$impWhere, $impParams] = $this->buildWhere($filters, 'impressions');
    [$evtWhere, $evtParams] = $this->buildWhere($filters, 'events');
    $imp = LogService::TABLE_IMPRESSION;
    $evt = LogService::TABLE_EVENT;

    $rows = [];
    foreach ($this->safeFetchAll(
      'SELECT arm, COUNT(*) AS impressions, COUNT(DISTINCT session_key) AS sessions, '
      . 'COUNT(DISTINCT seed_item_id) AS items, SUM(hop_depth >= 1) AS via_recommendation, '
      . 'AVG(duration_ms) AS avg_duration, SUM(is_empty) AS empties '
      . 'FROM ' . $imp . ' ' . $impWhere . ' GROUP BY arm',
      $impParams
    ) as $row) {
      $arm = (string) ($row['arm'] ?? '');
      $rows[$arm] = [
        'impressions' => (int) $row['impressions'],
        'sessions' => (int) $row['sessions'],
        'items' => (int) $row['items'],
        'via_recommendation' => (int) $row['via_recommendation'],
        'avg_duration_ms' => (float) $row['avg_duration'],
        'empties' => (int) $row['empties'],
        'views' => 0,
        'never_seen' => 0,
        'not_reported' => 0,
        'clicks' => 0,
        'cross' => 0,
        'same' => 0,
        'items_per_session' => 0.0,
      ];
    }
    if (!$rows) {
      return [];
    }

    foreach ($this->safeFetchAll(
      'SELECT arm, event_type, SUM(was_visible = 1) AS seen, COUNT(*) AS n FROM ' . $evt . ' '
      . $evtWhere . ' GROUP BY arm, event_type',
      $evtParams
    ) as $row) {
      $arm = (string) ($row['arm'] ?? '');
      if (!isset($rows[$arm])) {
        continue;
      }
      if ($row['event_type'] === 'view') {
        // Visible views only; see buildSummary() for why.
        $rows[$arm]['views'] = (int) $row['seen'];
        $rows[$arm]['never_seen'] = (int) $row['n'] - (int) $row['seen'];
      }
      elseif ($row['event_type'] === 'click') {
        $rows[$arm]['clicks'] = (int) $row['n'];
      }
    }

    foreach ($this->safeFetchAll(
      'SELECT arm, cross_domain, COUNT(*) AS n FROM ' . $evt . ' ' . $evtWhere
      . ' AND event_type = ? AND cross_domain IS NOT NULL GROUP BY arm, cross_domain',
      array_merge($evtParams, ['click'])
    ) as $row) {
      $arm = (string) ($row['arm'] ?? '');
      if (!isset($rows[$arm])) {
        continue;
      }
      $rows[$arm][((int) $row['cross_domain'] === 1) ? 'cross' : 'same'] += (int) $row['n'];
    }

    foreach ($this->safeFetchAll(
      'SELECT arm, AVG(c) AS avg_items FROM (SELECT arm, session_key, '
      . 'COUNT(DISTINCT seed_item_id) AS c FROM ' . $imp . ' ' . $impWhere
      . ' GROUP BY arm, session_key) t GROUP BY arm',
      $impParams
    ) as $row) {
      $arm = (string) ($row['arm'] ?? '');
      if (isset($rows[$arm])) {
        $rows[$arm]['items_per_session'] = (float) $row['avg_items'];
      }
    }

    foreach ($rows as $arm => $r) {
      $rows[$arm]['not_reported'] = max(0, $r['impressions'] - $r['views'] - $r['never_seen']);
      $rows[$arm]['pages_per_session'] = $r['sessions'] > 0 ? $r['impressions'] / $r['sessions'] : 0.0;
      $rows[$arm]['ctr'] = $r['impressions'] > 0 ? $r['clicks'] / $r['impressions'] : 0.0;
      $rows[$arm]['viewed_ctr'] = $r['views'] > 0 ? $r['clicks'] / $r['views'] : 0.0;
      $rows[$arm]['view_rate'] = $r['impressions'] > 0 ? $r['views'] / $r['impressions'] : 0.0;
      $rows[$arm]['via_rate'] = $r['impressions'] > 0 ? $r['via_recommendation'] / $r['impressions'] : 0.0;
      $classified = $r['cross'] + $r['same'];
      $rows[$arm]['cross_rate'] = $classified > 0 ? $r['cross'] / $classified : NULL;
    }

    // Report in a stable order, unknown arms last.
    $ordered = [];
    foreach (ArmAssigner::ARMS as $arm) {
      if (isset($rows[$arm])) {
        $ordered[$arm] = $rows[$arm];
      }
    }
    foreach ($rows as $arm => $r) {
      if (!isset($ordered[$arm])) {
        $ordered[$arm] = $r;
      }
    }
    return $ordered;
  }

  /**
   * Compute the dashboard figures for the current filter set.
   */
  private function buildSummary(array $filters): array {
    $out = [
      'impressions' => 0,
      'sessions' => 0,
      'seeds' => 0,
      'empty' => 0,
      'avg_duration_ms' => 0.0,
      'avg_candidates' => 0.0,
      'avg_results' => 0.0,
      'views' => 0,
      'never_seen' => 0,
      'not_reported' => 0,
      'clicks' => 0,
      'ctr' => 0.0,
      'viewed_ctr' => 0.0,
      'view_rate' => 0.0,
      'avg_click_dwell_ms' => 0.0,
      'ranks' => [],
      'hops' => [],
      'chains' => 0,
      'chains_with_hop' => 0,
      'avg_chain_len' => 0.0,
      'max_hop' => 0,
      'entry_kinds' => [],
      'devices' => [],
      'cross_domain_clicks' => 0,
      'same_domain_clicks' => 0,
      'top_seeds' => [],
      'top_targets' => [],
      'variants' => [],
    ];
    if (!$this->conn) {
      return $out;
    }
    [$impWhere, $impParams] = $this->buildWhere($filters, 'impressions');
    [$evtWhere, $evtParams] = $this->buildWhere($filters, 'events');
    $imp = LogService::TABLE_IMPRESSION;
    $evt = LogService::TABLE_EVENT;

    $head = $this->safeFetchOneRow(
      'SELECT COUNT(*) AS n, COUNT(DISTINCT session_key) AS sessions, '
      . 'COUNT(DISTINCT seed_item_id) AS seeds, SUM(is_empty) AS empties, '
      . 'AVG(duration_ms) AS avg_duration, AVG(candidate_count) AS avg_candidates, '
      . 'AVG(result_count) AS avg_results, MAX(hop_depth) AS max_hop, '
      . 'COUNT(DISTINCT chain_key) AS chains '
      . 'FROM ' . $imp . ' ' . $impWhere,
      $impParams
    );
    if ($head) {
      $out['impressions'] = (int) $head['n'];
      $out['sessions'] = (int) $head['sessions'];
      $out['seeds'] = (int) $head['seeds'];
      $out['empty'] = (int) $head['empties'];
      $out['avg_duration_ms'] = (float) $head['avg_duration'];
      $out['avg_candidates'] = (float) $head['avg_candidates'];
      $out['avg_results'] = (float) $head['avg_results'];
      $out['max_hop'] = (int) $head['max_hop'];
      $out['chains'] = (int) $head['chains'];
    }

    // A `view` row is written whether or not the block was ever on screen: the
    // client reports it on first intersection, and otherwise on page hide with
    // was_visible = 0. Counting both as "viewed" would defeat the purpose of
    // the event, which is to separate rendered from actually seen.
    $events = $this->safeFetchAll(
      'SELECT event_type, SUM(was_visible = 1) AS seen, COUNT(*) AS n FROM ' . $evt . ' '
      . $evtWhere . ' GROUP BY event_type',
      $evtParams
    );
    foreach ($events as $row) {
      if ($row['event_type'] === 'view') {
        $out['views'] = (int) $row['seen'];
        $out['never_seen'] = (int) $row['n'] - (int) $row['seen'];
      }
      elseif ($row['event_type'] === 'click') {
        $out['clicks'] = (int) $row['n'];
      }
    }
    // Impressions split into three, and they must add up: seen, reported as
    // never on screen, and never reported at all. The third happens when the
    // visitor leaves before the block is rendered, so the client never gets
    // the chance to observe it.
    $out['not_reported'] = max(0, $out['impressions'] - $out['views'] - $out['never_seen']);
    if ($out['impressions'] > 0) {
      $out['ctr'] = $out['clicks'] / $out['impressions'];
      // A lower bound: the unreported impressions may or may not have been seen.
      $out['view_rate'] = $out['views'] / $out['impressions'];
    }
    if ($out['views'] > 0) {
      $out['viewed_ctr'] = $out['clicks'] / $out['views'];
    }

    $dwell = $this->safeFetchOneRow(
      'SELECT AVG(dwell_ms) AS d FROM ' . $evt . ' ' . $evtWhere . ' AND event_type = ?',
      array_merge($evtParams, ['click'])
    );
    $out['avg_click_dwell_ms'] = $dwell ? (float) $dwell['d'] : 0.0;

    // Rank breakdown: impressions that offered rank r versus clicks on rank r.
    $resultCounts = $this->safeFetchAll(
      'SELECT result_count, COUNT(*) AS n FROM ' . $imp . ' ' . $impWhere . ' GROUP BY result_count',
      $impParams
    );
    $offered = array_fill(1, self::MAX_RANK, 0);
    foreach ($resultCounts as $row) {
      $rc = min(self::MAX_RANK, (int) $row['result_count']);
      for ($r = 1; $r <= $rc; $r++) {
        $offered[$r] += (int) $row['n'];
      }
    }
    $clicksByRank = $this->safeFetchAll(
      'SELECT target_rank, COUNT(*) AS n FROM ' . $evt . ' ' . $evtWhere
      . ' AND event_type = ? AND target_rank IS NOT NULL GROUP BY target_rank',
      array_merge($evtParams, ['click'])
    );
    $clicked = [];
    foreach ($clicksByRank as $row) {
      $clicked[(int) $row['target_rank']] = (int) $row['n'];
    }
    for ($r = 1; $r <= self::MAX_RANK; $r++) {
      if ($offered[$r] === 0 && empty($clicked[$r])) {
        continue;
      }
      $out['ranks'][$r] = [
        'offered' => $offered[$r],
        'clicks' => (int) ($clicked[$r] ?? 0),
        'ctr' => $offered[$r] > 0 ? (($clicked[$r] ?? 0) / $offered[$r]) : 0.0,
      ];
    }

    // Browsing depth.
    $hops = $this->safeFetchAll(
      'SELECT hop_depth, COUNT(*) AS n FROM ' . $imp . ' ' . $impWhere . ' GROUP BY hop_depth ORDER BY hop_depth',
      $impParams
    );
    foreach ($hops as $row) {
      $out['hops'][(int) $row['hop_depth']] = (int) $row['n'];
    }
    $chains = $this->safeFetchOneRow(
      'SELECT COUNT(*) AS n, AVG(c) AS avg_len FROM '
      . '(SELECT chain_key, COUNT(*) AS c, MAX(hop_depth) AS mh FROM ' . $imp . ' ' . $impWhere
      . ' GROUP BY chain_key) t WHERE t.mh >= 1',
      $impParams
    );
    if ($chains) {
      $out['chains_with_hop'] = (int) $chains['n'];
      $out['avg_chain_len'] = (float) $chains['avg_len'];
    }

    foreach ($this->safeFetchAll(
      'SELECT entry_kind, COUNT(*) AS n FROM ' . $imp . ' ' . $impWhere . ' GROUP BY entry_kind ORDER BY n DESC',
      $impParams
    ) as $row) {
      $out['entry_kinds'][(string) ($row['entry_kind'] ?? '')] = (int) $row['n'];
    }
    foreach ($this->safeFetchAll(
      'SELECT device, COUNT(*) AS n FROM ' . $imp . ' ' . $impWhere . ' GROUP BY device ORDER BY n DESC',
      $impParams
    ) as $row) {
      $out['devices'][(string) ($row['device'] ?? '')] = (int) $row['n'];
    }
    foreach ($this->safeFetchAll(
      'SELECT variant, COUNT(*) AS n FROM ' . $imp . ' ' . $impWhere . ' GROUP BY variant ORDER BY n DESC',
      $impParams
    ) as $row) {
      $out['variants'][(string) ($row['variant'] ?? '')] = (int) $row['n'];
    }

    $cross = $this->safeFetchAll(
      'SELECT cross_domain, COUNT(*) AS n FROM ' . $evt . ' ' . $evtWhere
      . ' AND event_type = ? AND cross_domain IS NOT NULL GROUP BY cross_domain',
      array_merge($evtParams, ['click'])
    );
    foreach ($cross as $row) {
      if ((int) $row['cross_domain'] === 1) {
        $out['cross_domain_clicks'] = (int) $row['n'];
      }
      else {
        $out['same_domain_clicks'] = (int) $row['n'];
      }
    }

    $out['top_seeds'] = $this->safeFetchAll(
      'SELECT seed_item_id, MIN(seed_item_title) AS title, COUNT(*) AS n FROM ' . $imp . ' ' . $impWhere
      . ' GROUP BY seed_item_id ORDER BY n DESC LIMIT 20',
      $impParams
    );
    $out['top_targets'] = $this->safeFetchAll(
      'SELECT target_item_id, COUNT(*) AS n FROM ' . $evt . ' ' . $evtWhere
      . ' AND event_type = ? AND target_item_id IS NOT NULL GROUP BY target_item_id ORDER BY n DESC LIMIT 20',
      array_merge($evtParams, ['click'])
    );

    return $out;
  }

  /**
   * Normalize filters from the query string.
   */
  private function getFilters(): array {
    $tz = $this->getTimeZone();
    $toTs = function (string $date, string $time) use ($tz) {
      if ($date === '') {
        return NULL;
      }
      try {
        $dt = new \DateTime($date . ' ' . $time, new \DateTimeZone($tz));
        $dt->setTimezone(new \DateTimeZone('UTC'));
        $ts = $dt->getTimestamp();
        return $ts > 0 ? $ts : NULL;
      }
      catch (\Throwable $e) {
        return NULL;
      }
    };
    $from = trim((string) $this->params()->fromQuery('from', ''));
    $to = trim((string) $this->params()->fromQuery('to', ''));
    return [
      'from' => $from,
      'to' => $to,
      'from_ts' => $toTs($from, '00:00:00'),
      'to_ts' => $toTs($to, '23:59:59'),
      'site_slug' => trim((string) $this->params()->fromQuery('site_slug', '')),
      'variant' => trim((string) $this->params()->fromQuery('variant', '')),
      'device' => trim((string) $this->params()->fromQuery('device', '')),
      'seed_item_id' => (int) $this->params()->fromQuery('seed_item_id', 0),
      'session_key' => trim((string) $this->params()->fromQuery('session_key', '')),
      'include_bots' => (int) $this->params()->fromQuery('include_bots', 0) === 1 ? 1 : 0,
    ];
  }

  /**
   * Build a WHERE clause shared by impressions and events.
   *
   * @return array
   *   [sql, params]. The clause always starts with WHERE so callers can append
   *   further "AND ..." fragments.
   */
  private function buildWhere(array $filters, string $dataset): array {
    $clauses = ['1 = 1'];
    $params = [];
    if (!empty($filters['from_ts'])) {
      $clauses[] = 'created_at >= ?';
      $params[] = (int) $filters['from_ts'];
    }
    if (!empty($filters['to_ts'])) {
      $clauses[] = 'created_at <= ?';
      $params[] = (int) $filters['to_ts'];
    }
    if ($filters['site_slug'] !== '') {
      $clauses[] = 'site_slug = ?';
      $params[] = $filters['site_slug'];
    }
    if ($filters['variant'] !== '') {
      $clauses[] = 'variant = ?';
      $params[] = $filters['variant'];
    }
    if ($filters['device'] !== '') {
      $clauses[] = 'device = ?';
      $params[] = $filters['device'];
    }
    if (!empty($filters['seed_item_id'])) {
      $clauses[] = 'seed_item_id = ?';
      $params[] = (int) $filters['seed_item_id'];
    }
    if ($filters['session_key'] !== '') {
      $clauses[] = 'session_key = ?';
      $params[] = $filters['session_key'];
    }
    if (empty($filters['include_bots'])) {
      $clauses[] = 'is_bot = 0';
    }
    return ['WHERE ' . implode(' AND ', $clauses), $params];
  }

  /**
   * Run a query, returning an empty list on failure.
   */
  private function safeFetchAll(string $sql, array $params): array {
    if (!$this->conn) {
      return [];
    }
    try {
      return $this->conn->fetchAllAssociative($sql, $params);
    }
    catch (\Throwable $e) {
      return [];
    }
  }

  /**
   * Run a query expected to return a single row.
   */
  private function safeFetchOneRow(string $sql, array $params): ?array {
    if (!$this->conn) {
      return NULL;
    }
    try {
      $row = $this->conn->fetchAssociative($sql, $params);
      return $row ?: NULL;
    }
    catch (\Throwable $e) {
      return NULL;
    }
  }

  /**
   * System time zone from global settings.
   */
  private function getTimeZone(): string {
    try {
      $services = $this->getEvent()->getApplication()->getServiceManager();
      if ($services->has('Omeka\Settings')) {
        return (string) $services->get('Omeka\Settings')->get('time_zone', 'UTC');
      }
    }
    catch (\Throwable $e) {
      // Fall through.
    }
    return 'UTC';
  }

  /**
   * Parse an HTML5 datetime-local value expressed in the system time zone.
   */
  private function parseDateTimeLocal(string $value): ?int {
    $value = trim($value);
    if ($value === '') {
      return NULL;
    }
    try {
      $dt = new \DateTime(str_replace('T', ' ', $value), new \DateTimeZone($this->getTimeZone()));
      $dt->setTimezone(new \DateTimeZone('UTC'));
      $ts = $dt->getTimestamp();
      return $ts > 0 ? $ts : NULL;
    }
    catch (\Throwable $e) {
      return NULL;
    }
  }

}
