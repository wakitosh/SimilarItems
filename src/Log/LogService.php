<?php

/**
 * @file
 * Usage-log collection service for the SimilarItems module.
 */

declare(strict_types=1);

namespace SimilarItems\Log;

use Doctrine\DBAL\Connection;
use Omeka\Settings\Settings;

/**
 * Collects impression/interaction logs for SimilarItems recommendations.
 *
 * The data model is intentionally research oriented:
 *
 * - similaritems_impression: one row per recommendation request served. Because
 *   the block is rendered on every item page that carries it, an impression row
 *   also works as a proxy for "item page view" and therefore carries the
 *   browsing path (回遊) of a session.
 * - similaritems_event: client side events attached to an impression
 *   (viewport view / link click). Clicks carry rank and score so that
 *   position bias and score-response relationships can be analysed.
 *
 * Chains (推薦をたどった連鎖) are resolved server side: when an impression is
 * created for a seed item that the same session clicked on moments earlier, the
 * new impression inherits the chain key and increments the hop depth.
 */
class LogService {

  /**
   * Impression table name.
   */
  public const TABLE_IMPRESSION = 'similaritems_impression';

  /**
   * Event table name.
   */
  public const TABLE_EVENT = 'similaritems_event';

  /**
   * Name of the first-party session cookie.
   */
  public const COOKIE_SESSION = 'si_slog';

  /**
   * Event types accepted from the client.
   */
  public const EVENT_TYPES = ['view', 'click'];

  /**
   * Maximum number of click events stored per impression.
   */
  private const MAX_CLICKS_PER_IMPRESSION = 20;

  /**
   * Database connection.
   *
   * @var \Doctrine\DBAL\Connection|null
   */
  private $conn;

  /**
   * Global settings.
   *
   * @var \Omeka\Settings\Settings
   */
  private Settings $settings;

  /**
   * Optional logger for internal failures.
   *
   * @var mixed
   */
  private $logger;

  /**
   * Cached session key for the current request.
   *
   * @var string|null
   */
  private ?string $sessionKey = NULL;

  /**
   * Whether ensureTables() already ran in this request.
   *
   * @var bool
   */
  private bool $tablesChecked = FALSE;

  /**
   * Constructor.
   */
  public function __construct(?Connection $conn, Settings $settings, $logger = NULL) {
    $this->conn = $conn;
    $this->settings = $settings;
    $this->logger = $logger;
  }

  /**
   * Whether usage logging is switched on.
   */
  public function isEnabled(): bool {
    if (!$this->conn) {
      return FALSE;
    }
    return (int) ($this->settings->get('similaritems.log.enable') ?? 0) === 1;
  }

  /**
   * Expose the connection (admin controllers reuse it).
   */
  public function getConnection(): ?Connection {
    return $this->conn;
  }

  /**
   * Create the log tables when missing.
   *
   * Cheap on MySQL/MariaDB when the tables already exist, and it keeps the
   * module working when it was updated without re-running install().
   */
  public function ensureTables(bool $force = FALSE): void {
    if (!$this->conn || (!$force && $this->tablesChecked)) {
      return;
    }
    $this->tablesChecked = TRUE;
    try {
      foreach ($this->getDdl() as $sql) {
        $this->conn->executeStatement($sql);
      }
      $this->ensureColumns();
    }
    catch (\Throwable $e) {
      $this->safeLog('SimilarItems log: cannot create tables: ' . $e->getMessage());
    }
  }

  /**
   * Add columns introduced after a table was first created.
   *
   * CREATE TABLE IF NOT EXISTS leaves an existing table untouched, so a site
   * that started logging on an earlier version would silently miss new fields.
   */
  private function ensureColumns(): void {
    $added = [
      self::TABLE_IMPRESSION => [
        'arm' => "ALTER TABLE `%s` ADD COLUMN `arm` VARCHAR(16) DEFAULT NULL AFTER `variant`, ADD KEY `idx_arm` (`arm`)",
      ],
      self::TABLE_EVENT => [
        'arm' => "ALTER TABLE `%s` ADD COLUMN `arm` VARCHAR(16) DEFAULT NULL AFTER `variant`, ADD KEY `idx_arm` (`arm`)",
      ],
    ];
    foreach ($added as $table => $columns) {
      foreach ($columns as $column => $ddl) {
        try {
          $exists = $this->conn->fetchOne(
            'SELECT COUNT(*) FROM information_schema.COLUMNS '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
          );
          if ((int) $exists === 0) {
            $this->conn->executeStatement(sprintf($ddl, $table));
          }
        }
        catch (\Throwable $e) {
          $this->safeLog('SimilarItems log: cannot add column ' . $table . '.' . $column . ': ' . $e->getMessage());
        }
      }
    }
  }

  /**
   * DDL statements for the log tables.
   *
   * @return string[]
   *   List of CREATE TABLE statements.
   */
  public function getDdl(): array {
    $impression = <<<SQL
CREATE TABLE IF NOT EXISTS `similaritems_impression` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `created_at` INT UNSIGNED NOT NULL,
  `impression_key` CHAR(32) NOT NULL,
  `session_key` CHAR(32) DEFAULT NULL,
  `visitor_key` CHAR(32) DEFAULT NULL,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `site_slug` VARCHAR(190) DEFAULT NULL,
  `locale` VARCHAR(16) DEFAULT NULL,
  `seed_item_id` INT UNSIGNED NOT NULL,
  `seed_item_title` VARCHAR(255) DEFAULT NULL,
  `seed_buckets` VARCHAR(255) DEFAULT NULL,
  `seed_item_sets` VARCHAR(255) DEFAULT NULL,
  `requested_limit` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `result_count` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `candidate_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `duration_ms` INT UNSIGNED NOT NULL DEFAULT 0,
  `is_empty` TINYINT(1) NOT NULL DEFAULT 0,
  `results` MEDIUMTEXT,
  `variant` VARCHAR(64) DEFAULT NULL,
  `arm` VARCHAR(16) DEFAULT NULL,
  `config_hash` CHAR(12) DEFAULT NULL,
  `tiebreak` VARCHAR(32) DEFAULT NULL,
  `jitter` TINYINT(1) NOT NULL DEFAULT 0,
  `parent_event_id` BIGINT UNSIGNED DEFAULT NULL,
  `parent_impression_id` BIGINT UNSIGNED DEFAULT NULL,
  `chain_key` CHAR(32) DEFAULT NULL,
  `hop_depth` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `entry_kind` VARCHAR(24) DEFAULT NULL,
  `referrer_host` VARCHAR(190) DEFAULT NULL,
  `referrer` VARCHAR(1024) DEFAULT NULL,
  `user_agent` VARCHAR(512) DEFAULT NULL,
  `device` VARCHAR(16) DEFAULT NULL,
  `is_bot` TINYINT(1) NOT NULL DEFAULT 0,
  `client_ip` VARCHAR(64) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_impression_key` (`impression_key`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_session_created` (`session_key`, `created_at`),
  KEY `idx_seed_item` (`seed_item_id`),
  KEY `idx_chain` (`chain_key`),
  KEY `idx_variant` (`variant`),
  KEY `idx_arm` (`arm`),
  KEY `idx_is_bot` (`is_bot`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

    $event = <<<SQL
CREATE TABLE IF NOT EXISTS `similaritems_event` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `created_at` INT UNSIGNED NOT NULL,
  `event_key` CHAR(32) NOT NULL,
  `event_type` VARCHAR(16) NOT NULL,
  `impression_id` BIGINT UNSIGNED DEFAULT NULL,
  `impression_key` CHAR(32) DEFAULT NULL,
  `session_key` CHAR(32) DEFAULT NULL,
  `visitor_key` CHAR(32) DEFAULT NULL,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `site_slug` VARCHAR(190) DEFAULT NULL,
  `variant` VARCHAR(64) DEFAULT NULL,
  `arm` VARCHAR(16) DEFAULT NULL,
  `seed_item_id` INT UNSIGNED DEFAULT NULL,
  `target_item_id` INT UNSIGNED DEFAULT NULL,
  `target_rank` SMALLINT UNSIGNED DEFAULT NULL,
  `target_score` FLOAT DEFAULT NULL,
  `target_signals` VARCHAR(1024) DEFAULT NULL,
  `target_bucket` VARCHAR(64) DEFAULT NULL,
  `cross_domain` TINYINT(1) DEFAULT NULL,
  `dwell_ms` INT UNSIGNED DEFAULT NULL,
  `visible_ms` INT UNSIGNED DEFAULT NULL,
  `was_visible` TINYINT(1) NOT NULL DEFAULT 0,
  `consumed` TINYINT(1) NOT NULL DEFAULT 0,
  `device` VARCHAR(16) DEFAULT NULL,
  `is_bot` TINYINT(1) NOT NULL DEFAULT 0,
  `client_ip` VARCHAR(64) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_event_key` (`event_key`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_event_type` (`event_type`),
  KEY `idx_arm` (`arm`),
  KEY `idx_impression_key` (`impression_key`),
  KEY `idx_session_created` (`session_key`, `created_at`),
  KEY `idx_target_item` (`target_item_id`),
  KEY `idx_chain_lookup` (`session_key`, `event_type`, `target_item_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

    return [$impression, $event];
  }

  /**
   * Store one impression (a recommendation request that was served).
   *
   * @param array $data
   *   Impression payload. Recognised keys mirror the table columns; `results`
   *   may be passed as an array and is JSON encoded here.
   *
   * @return array|null
   *   ['id' => int, 'impression_key' => string, 'chain_key' => string,
   *   'hop_depth' => int] or NULL when nothing was written.
   */
  public function recordImpression(array $data): ?array {
    if (!$this->isEnabled()) {
      return NULL;
    }
    $this->ensureTables();
    $isBot = $this->isBot();
    if ($isBot && (int) ($this->settings->get('similaritems.log.exclude_bots') ?? 1) === 1) {
      return NULL;
    }
    try {
      $now = time();
      $key = $this->randomKey();
      $sessionKey = $this->getSessionKey();
      $results = $data['results'] ?? [];
      $resultCount = is_array($results) ? count($results) : 0;
      $seedItemId = (int) ($data['seed_item_id'] ?? 0);

      // Resolve the browsing chain from the click that led here (if any).
      $chain = $this->resolveChain($sessionKey, $seedItemId, $now);
      // NOTE: this request is an XHR issued by the item page itself, so its
      // Referer header is that same page and says nothing about how the
      // visitor arrived. The real referrer is reported by the browser with the
      // `view` event (document.referrer) and filled in by applyClientReferrer().

      $record = [
        'created_at' => $now,
        'impression_key' => $key,
        'session_key' => $sessionKey,
        'visitor_key' => $this->getVisitorKey(),
        'user_id' => $this->normalizeUserId($data['user_id'] ?? NULL),
        'site_slug' => $this->clip($data['site_slug'] ?? NULL, 190),
        'locale' => $this->clip($data['locale'] ?? NULL, 16),
        'seed_item_id' => $seedItemId,
        'seed_item_title' => $this->clip($data['seed_item_title'] ?? NULL, 255),
        'seed_buckets' => $this->clip($data['seed_buckets'] ?? NULL, 255),
        'seed_item_sets' => $this->clip($data['seed_item_sets'] ?? NULL, 255),
        'requested_limit' => (int) ($data['requested_limit'] ?? 0),
        'result_count' => $resultCount,
        'candidate_count' => (int) ($data['candidate_count'] ?? 0),
        'duration_ms' => (int) ($data['duration_ms'] ?? 0),
        'is_empty' => $resultCount > 0 ? 0 : 1,
        'results' => is_array($results)
          ? json_encode($results, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
          : (string) $results,
        'variant' => $this->clip($data['variant'] ?? $this->getVariant(), 64),
        'arm' => $this->clip($data['arm'] ?? NULL, 16),
        'config_hash' => $this->clip($data['config_hash'] ?? NULL, 12),
        'tiebreak' => $this->clip($data['tiebreak'] ?? NULL, 32),
        'jitter' => !empty($data['jitter']) ? 1 : 0,
        'parent_event_id' => $chain['parent_event_id'],
        'parent_impression_id' => $chain['parent_impression_id'],
        'chain_key' => $chain['chain_key'] ?? $key,
        'hop_depth' => (int) ($chain['hop_depth'] ?? 0),
        'entry_kind' => $chain['parent_event_id'] ? 'similar_items' : NULL,
        'referrer_host' => NULL,
        'referrer' => NULL,
        'user_agent' => $this->clip($this->getUserAgent(), 512),
        'device' => $this->getDevice(),
        'is_bot' => $isBot ? 1 : 0,
        'client_ip' => $this->getClientIpForStorage(),
      ];
      if ($record['chain_key'] === NULL) {
        $record['chain_key'] = $key;
      }
      $this->conn->insert(self::TABLE_IMPRESSION, $record);
      $id = (int) $this->conn->lastInsertId();

      return [
        'id' => $id,
        'impression_key' => $key,
        'chain_key' => (string) $record['chain_key'],
        'hop_depth' => (int) $record['hop_depth'],
        'session_key' => $sessionKey,
      ];
    }
    catch (\Throwable $e) {
      $this->safeLog('SimilarItems impression log failed: ' . $e->getMessage());
      return NULL;
    }
  }

  /**
   * Store a client-side event (viewport view or click) for an impression.
   *
   * @param array $data
   *   Raw payload coming from the browser. Everything is validated here.
   *
   * @return bool
   *   TRUE when a row was written.
   */
  public function recordEvent(array $data): bool {
    if (!$this->isEnabled()) {
      return FALSE;
    }
    $this->ensureTables();
    $isBot = $this->isBot();
    if ($isBot && (int) ($this->settings->get('similaritems.log.exclude_bots') ?? 1) === 1) {
      return FALSE;
    }

    $type = strtolower(trim((string) ($data['type'] ?? '')));
    if (!in_array($type, self::EVENT_TYPES, TRUE)) {
      return FALSE;
    }
    $impressionKey = $this->normalizeKey($data['impression'] ?? NULL);
    if ($impressionKey === NULL) {
      return FALSE;
    }

    try {
      $impression = $this->conn->fetchAssociative(
        'SELECT id, impression_key, session_key, site_slug, seed_item_id, seed_buckets, variant, arm, chain_key, hop_depth, parent_event_id, entry_kind, results '
        . 'FROM ' . self::TABLE_IMPRESSION . ' WHERE impression_key = ? LIMIT 1',
        [$impressionKey]
      );
    }
    catch (\Throwable $e) {
      $impression = FALSE;
    }
    if (!$impression) {
      return FALSE;
    }

    // Guard against duplicate/abusive submissions.
    if (!$this->acceptEvent($type, $impressionKey)) {
      return FALSE;
    }

    $eventKey = $this->normalizeKey($data['key'] ?? NULL) ?? $this->randomKey();
    $targetItemId = isset($data['item_id']) ? (int) $data['item_id'] : 0;
    $known = $this->lookupResult($impression['results'] ?? '', $targetItemId);

    $record = [
      'created_at' => time(),
      'event_key' => $eventKey,
      'event_type' => $type,
      'impression_id' => (int) $impression['id'],
      'impression_key' => $impressionKey,
      'session_key' => $impression['session_key'] ?: $this->getSessionKey(),
      'visitor_key' => $this->getVisitorKey(),
      'user_id' => $this->normalizeUserId($data['user_id'] ?? NULL),
      'site_slug' => $this->clip($impression['site_slug'] ?? NULL, 190),
      'variant' => $this->clip($impression['variant'] ?? NULL, 64),
      'arm' => $this->clip($impression['arm'] ?? NULL, 16),
      'seed_item_id' => (int) ($impression['seed_item_id'] ?? 0),
      'target_item_id' => $targetItemId > 0 ? $targetItemId : NULL,
      // Rank/score are taken from the stored impression, never from the
      // client, so that they cannot be forged.
      'target_rank' => $known['rank'] ?? NULL,
      'target_score' => $known['score'] ?? NULL,
      'target_signals' => $this->clip($known['signals'] ?? NULL, 1024),
      'target_bucket' => $this->clip($known['bucket'] ?? NULL, 64),
      'cross_domain' => $this->isCrossDomain($impression['seed_buckets'] ?? NULL, $known['bucket'] ?? NULL),
      'dwell_ms' => $this->normalizeDuration($data['dwell_ms'] ?? NULL),
      'visible_ms' => $this->normalizeDuration($data['visible_ms'] ?? NULL),
      'was_visible' => !empty($data['visible']) ? 1 : 0,
      'consumed' => 0,
      'device' => $this->getDevice(),
      'is_bot' => $isBot ? 1 : 0,
      'client_ip' => $this->getClientIpForStorage(),
    ];
    if ($type === 'click' && $targetItemId <= 0) {
      // A click without a resolvable target is useless for analysis.
      return FALSE;
    }

    try {
      $this->conn->insert(self::TABLE_EVENT, $record);
    }
    catch (\Throwable $e) {
      // Duplicate event_key (retried beacon) is not an error worth logging.
      return FALSE;
    }

    // Only the browser knows how the visitor reached the page, so the `view`
    // event carries document.referrer.
    if ($type === 'view' && array_key_exists('ref', $data)) {
      $this->applyClientReferrer(
        (int) $impression['id'],
        is_string($data['ref']) ? $data['ref'] : '',
        $impression['entry_kind'] ?? NULL
      );
    }

    // The client may tell us which click led to this impression; use it to
    // correct the server-side heuristic when that found nothing.
    $from = $this->normalizeKey($data['from'] ?? NULL);
    if ($from !== NULL && empty($impression['parent_event_id'])) {
      $this->linkImpressionToClick((int) $impression['id'], $from);
    }
    return TRUE;
  }

  /**
   * Delete rows older than the configured retention window.
   *
   * @return int
   *   Number of impression rows removed.
   */
  public function purgeExpired(): int {
    $days = (int) ($this->settings->get('similaritems.log.retention_days') ?? 0);
    if (!$this->conn || $days <= 0) {
      return 0;
    }
    $cut = time() - ($days * 86400);
    try {
      $this->conn->executeStatement('DELETE FROM ' . self::TABLE_EVENT . ' WHERE created_at < ?', [$cut]);
      return (int) $this->conn->executeStatement(
        'DELETE FROM ' . self::TABLE_IMPRESSION . ' WHERE created_at < ?',
        [$cut]
      );
    }
    catch (\Throwable $e) {
      $this->safeLog('SimilarItems log purge failed: ' . $e->getMessage());
      return 0;
    }
  }

  /**
   * Find the click that most likely led to this impression.
   *
   * @return array
   *   Keys: parent_event_id, parent_impression_id, chain_key, hop_depth.
   */
  private function resolveChain(?string $sessionKey, int $seedItemId, int $now): array {
    $empty = [
      'parent_event_id' => NULL,
      'parent_impression_id' => NULL,
      'chain_key' => NULL,
      'hop_depth' => 0,
    ];
    if (!$sessionKey || $seedItemId <= 0) {
      return $empty;
    }
    $window = max(30, (int) ($this->settings->get('similaritems.log.chain_window') ?? 300));
    try {
      $row = $this->conn->fetchAssociative(
        'SELECT id, impression_id FROM ' . self::TABLE_EVENT . ' '
        . 'WHERE session_key = ? AND event_type = ? AND target_item_id = ? '
        . 'AND consumed = 0 AND created_at >= ? ORDER BY id DESC LIMIT 1',
        [$sessionKey, 'click', $seedItemId, $now - $window]
      );
      if (!$row) {
        return $empty;
      }
      $parentImpression = $this->conn->fetchAssociative(
        'SELECT id, chain_key, hop_depth FROM ' . self::TABLE_IMPRESSION . ' WHERE id = ? LIMIT 1',
        [(int) $row['impression_id']]
      );
      // Mark the click as consumed so a later impression cannot reuse it.
      $this->conn->executeStatement(
        'UPDATE ' . self::TABLE_EVENT . ' SET consumed = 1 WHERE id = ?',
        [(int) $row['id']]
      );
      return [
        'parent_event_id' => (int) $row['id'],
        'parent_impression_id' => $parentImpression ? (int) $parentImpression['id'] : NULL,
        'chain_key' => $parentImpression ? (string) $parentImpression['chain_key'] : NULL,
        'hop_depth' => $parentImpression ? ((int) $parentImpression['hop_depth'] + 1) : 1,
      ];
    }
    catch (\Throwable $e) {
      return $empty;
    }
  }

  /**
   * Record how the visitor reached the page, as reported by the browser.
   *
   * An impression already attributed to a recommendation click keeps that
   * attribution: the chain is a stronger signal than the referrer.
   */
  private function applyClientReferrer(int $impressionId, string $referrer, ?string $currentEntryKind): void {
    if ($currentEntryKind === 'similar_items') {
      return;
    }
    $referrer = trim($referrer);
    try {
      $this->conn->update(
        self::TABLE_IMPRESSION,
        [
          'referrer' => $this->clip($referrer, 1024),
          'referrer_host' => $this->clip($this->hostOf($referrer), 190),
          'entry_kind' => $this->classifyReferrer($referrer),
        ],
        ['id' => $impressionId]
      );
    }
    catch (\Throwable $e) {
      // Entry classification is best effort.
    }
  }

  /**
   * Attach an impression to a click reported by the browser.
   */
  private function linkImpressionToClick(int $impressionId, string $clickKey): void {
    try {
      $click = $this->conn->fetchAssociative(
        'SELECT id, impression_id FROM ' . self::TABLE_EVENT . ' WHERE event_key = ? AND event_type = ? LIMIT 1',
        [$clickKey, 'click']
      );
      if (!$click) {
        return;
      }
      $parent = $this->conn->fetchAssociative(
        'SELECT id, chain_key, hop_depth FROM ' . self::TABLE_IMPRESSION . ' WHERE id = ? LIMIT 1',
        [(int) $click['impression_id']]
      );
      $this->conn->update(
        self::TABLE_IMPRESSION,
        [
          'parent_event_id' => (int) $click['id'],
          'parent_impression_id' => $parent ? (int) $parent['id'] : NULL,
          'chain_key' => $parent ? (string) $parent['chain_key'] : NULL,
          'hop_depth' => $parent ? ((int) $parent['hop_depth'] + 1) : 1,
          'entry_kind' => 'similar_items',
        ],
        ['id' => $impressionId]
      );
      $this->conn->executeStatement(
        'UPDATE ' . self::TABLE_EVENT . ' SET consumed = 1 WHERE id = ?',
        [(int) $click['id']]
      );
    }
    catch (\Throwable $e) {
      // Chain correction is best effort.
    }
  }

  /**
   * Get (and refresh) the pseudonymous session key.
   *
   * The key is a random token stored in a first-party cookie with a sliding
   * expiry. It carries no personal data and is only used to reconstruct a
   * browsing path.
   */
  public function getSessionKey(): ?string {
    if ($this->sessionKey !== NULL) {
      return $this->sessionKey ?: NULL;
    }
    if ((int) ($this->settings->get('similaritems.log.session_cookie') ?? 1) !== 1) {
      // Fall back to the daily visitor key so sessions are still approximated.
      $this->sessionKey = (string) ($this->getVisitorKey() ?? '');
      return $this->sessionKey ?: NULL;
    }
    $ttl = max(300, (int) ($this->settings->get('similaritems.log.session_ttl') ?? 1800));
    $key = $this->normalizeKey($_COOKIE[self::COOKIE_SESSION] ?? NULL);
    if ($key === NULL) {
      $key = $this->randomKey();
    }
    // Refresh the cookie on every request for a sliding session window.
    if (!headers_sent()) {
      @setcookie(self::COOKIE_SESSION, $key, [
        'expires' => time() + $ttl,
        'path' => '/',
        'secure' => $this->isHttps(),
        'httponly' => TRUE,
        'samesite' => 'Lax',
      ]);
    }
    $_COOKIE[self::COOKIE_SESSION] = $key;
    $this->sessionKey = $key;
    return $key;
  }

  /**
   * Cookie-less, daily-rotating pseudonymous visitor key.
   *
   * Used as a fallback grouping key when cookies are refused. It rotates every
   * day so it cannot be used for long term tracking.
   */
  public function getVisitorKey(): ?string {
    $ip = $this->getClientIp();
    $ua = $this->getUserAgent();
    if ($ip === NULL && $ua === '') {
      return NULL;
    }
    $raw = ($ip ?? '') . '|' . $ua . '|' . gmdate('Y-m-d') . '|' . $this->getSalt();
    return substr(hash('sha256', $raw), 0, 32);
  }

  /**
   * Client IP as it should be persisted (hashed, raw or dropped).
   */
  public function getClientIpForStorage(): ?string {
    $mode = (string) ($this->settings->get('similaritems.log.ip_mode') ?? 'hash');
    $ip = $this->getClientIp();
    if ($ip === NULL || $mode === 'none') {
      return NULL;
    }
    if ($mode === 'raw') {
      return substr($ip, 0, 64);
    }
    return substr(hash('sha256', $ip . '|' . $this->getSalt()), 0, 64);
  }

  /**
   * Raw client IP from the request.
   */
  public function getClientIp(): ?string {
    foreach (['HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'] as $k) {
      if (empty($_SERVER[$k])) {
        continue;
      }
      $v = (string) $_SERVER[$k];
      if ($k === 'HTTP_X_FORWARDED_FOR' && strpos($v, ',') !== FALSE) {
        $v = trim(explode(',', $v)[0]);
      }
      return $v !== '' ? $v : NULL;
    }
    return NULL;
  }

  /**
   * Salt used for hashing, created and stored once if it does not exist yet.
   *
   * Called at install time and whenever logging is switched on, so that the
   * salt is fixed before the first row is written rather than appearing
   * halfway through a study. It is never regenerated: changing it would make
   * existing hashes incomparable with new ones.
   *
   * Analysis note: the same salt reproduces `client_ip` from a raw address,
   * which is what allows this log to be joined to a web server access log:
   *
   *   client_ip = substr(sha256(<ip> . '|' . <salt>), 0, 64)
   *
   * Treat the salt as confidential. Publishing it alongside the hashes would
   * make them reversible, because the IPv4 space is small enough to enumerate.
   *
   * @return string
   *   The salt, or an empty string when it could not be stored.
   */
  public function ensureSalt(): string {
    $salt = (string) ($this->settings->get('similaritems.log.salt') ?? '');
    if ($salt !== '') {
      return $salt;
    }
    try {
      $salt = bin2hex(random_bytes(16));
    }
    catch (\Throwable $e) {
      $salt = hash('sha256', uniqid('si', TRUE) . microtime(TRUE));
    }
    try {
      $this->settings->set('similaritems.log.salt', $salt);
    }
    catch (\Throwable $e) {
      $this->safeLog('SimilarItems log: cannot store the hashing salt: ' . $e->getMessage());
      return '';
    }
    return $salt;
  }

  /**
   * Salt used for hashing.
   */
  private function getSalt(): string {
    return $this->ensureSalt();
  }

  /**
   * User agent string of the current request.
   */
  public function getUserAgent(): string {
    return isset($_SERVER['HTTP_USER_AGENT']) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';
  }

  /**
   * Heuristic bot detection based on the user agent.
   */
  public function isBot(): bool {
    $ua = $this->getUserAgent();
    if ($ua === '') {
      // No user agent at all is almost always automation.
      return TRUE;
    }
    $pattern = '/(bot|crawler|crawling|spider|slurp|archiver|scrapy|wget|curl|'
      . 'python-requests|python-urllib|httpclient|okhttp|java\/|libwww|go-http-client|'
      . 'headlesschrome|phantomjs|puppeteer|playwright|lighthouse|pingdom|uptimerobot|'
      . 'facebookexternalhit|feedfetcher|mediapartners|bingpreview|yandex|baiduspider|'
      . 'petalbot|bytespider|gptbot|claudebot|ccbot|applebot|amazonbot|duckduckbot|'
      . 'semrush|ahrefs|mj12|dotbot|dataforseo|serpstat|siteaudit)/i';
    return (bool) preg_match($pattern, $ua);
  }

  /**
   * Coarse device class derived from the user agent.
   */
  public function getDevice(): string {
    $ua = $this->getUserAgent();
    if ($ua === '') {
      return 'unknown';
    }
    if (preg_match('/(ipad|tablet|playbook|silk|android(?!.*mobile))/i', $ua)) {
      return 'tablet';
    }
    if (preg_match('/(mobile|iphone|ipod|android|blackberry|windows phone)/i', $ua)) {
      return 'mobile';
    }
    return 'desktop';
  }

  /**
   * Classify how the visitor reached the page carrying the block.
   *
   * Expects the browser-reported document.referrer. An empty value means a
   * direct visit (typed URL, bookmark, or a referrer stripped by policy).
   */
  private function classifyReferrer(string $referrer): string {
    if ($referrer === '') {
      return 'direct';
    }
    $host = $this->hostOf($referrer);
    if ($host === NULL) {
      return 'external';
    }
    $selfHost = isset($_SERVER['HTTP_HOST']) ? strtolower((string) $_SERVER['HTTP_HOST']) : '';
    if ($selfHost !== '' && strpos($selfHost, ':') !== FALSE) {
      $selfHost = (string) strstr($selfHost, ':', TRUE);
    }
    if ($selfHost !== '' && $host === $selfHost) {
      return 'internal';
    }
    if (preg_match('/(google|bing|yahoo|duckduckgo|baidu|yandex|ecosia|naver)\./i', $host)) {
      return 'search_engine';
    }
    if (preg_match('/(twitter|x\.com|facebook|instagram|t\.co|mastodon|bsky|reddit|line\.me)/i', $host)) {
      return 'social';
    }
    return 'external';
  }

  /**
   * Host part of a URL, lowercased.
   */
  private function hostOf(string $url): ?string {
    if ($url === '') {
      return NULL;
    }
    $host = parse_url($url, PHP_URL_HOST);
    return is_string($host) && $host !== '' ? strtolower($host) : NULL;
  }

  /**
   * Whether the current request is over HTTPS.
   */
  private function isHttps(): bool {
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
      return TRUE;
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
      return strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https';
    }
    return FALSE;
  }

  /**
   * Current A/B arm label, if the operator configured one.
   */
  public function getVariant(): ?string {
    $variant = trim((string) ($this->settings->get('similaritems.log.variant') ?? ''));
    return $variant !== '' ? $variant : NULL;
  }

  /**
   * Short hash of the effective scoring configuration.
   *
   * Lets a paper distinguish periods where tuning changed, without storing the
   * whole configuration on every row.
   */
  public function computeConfigHash(array $extra = []): string {
    $keys = [
      'similaritems.scope_site',
      'similaritems.use_item_sets',
      'similaritems.limit',
      'similaritems.tiebreak_policy',
      'similaritems.jitter.enable',
      'similaritems.jitter.pool_multiplier',
      'similaritems.weight.bibid',
      'similaritems.weight.author_id',
      'similaritems.weight.authorized_name',
      'similaritems.weight.subject',
      'similaritems.weight.domain_bucket',
      'similaritems.weight.call_shelf',
      'similaritems.weight.series_title',
      'similaritems.weight.publisher',
      'similaritems.weight_item_sets',
      'similaritems.weight.class_proximity',
      'similaritems.weight.material_type',
      'similaritems.weight.issued_proximity',
      'similaritems.weight.publication_place',
      'similaritems.issued_proximity_threshold',
      'similaritems.class_proximity_threshold',
      'similaritems.serendipity.demote_same_bibid',
      'similaritems.serendipity.same_bibid_penalty',
      'similaritems.serendipity.same_title_penalty',
      'similaritems.serendipity.same_title_mode',
      'similaritems.multi_match.enable',
      'similaritems.multi_match.decay',
    ];
    $payload = [];
    foreach ($keys as $k) {
      $payload[$k] = $this->settings->get($k);
    }
    foreach ($extra as $k => $v) {
      $payload['@' . $k] = $v;
    }
    return substr(hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE)), 0, 12);
  }

  /**
   * Generate a 32 hex character token.
   */
  public function randomKey(): string {
    try {
      return bin2hex(random_bytes(16));
    }
    catch (\Throwable $e) {
      return substr(hash('sha256', uniqid('si', TRUE) . mt_rand()), 0, 32);
    }
  }

  /**
   * Validate a 32 hex character token coming from cookie or client.
   */
  private function normalizeKey($value): ?string {
    if (!is_string($value)) {
      return NULL;
    }
    $value = trim($value);
    return preg_match('/^[0-9a-f]{32}$/', $value) ? $value : NULL;
  }

  /**
   * Clamp a client supplied duration to a sane range.
   */
  private function normalizeDuration($value): ?int {
    if ($value === NULL || $value === '') {
      return NULL;
    }
    $ms = (int) $value;
    if ($ms < 0) {
      return NULL;
    }
    // Anything above 24h is noise (clock skew, suspended tabs).
    return min($ms, 86400000);
  }

  /**
   * Normalize a user id, honouring the "store user id" setting.
   */
  private function normalizeUserId($value): ?int {
    if ((int) ($this->settings->get('similaritems.log.store_user') ?? 0) !== 1) {
      return NULL;
    }
    $id = (int) $value;
    return $id > 0 ? $id : NULL;
  }

  /**
   * Truncate a value for a VARCHAR column.
   */
  private function clip($value, int $max): ?string {
    if ($value === NULL) {
      return NULL;
    }
    $s = trim((string) $value);
    if ($s === '') {
      return NULL;
    }
    return function_exists('mb_substr') ? mb_substr($s, 0, $max) : substr($s, 0, $max);
  }

  /**
   * Whether a clicked item sits outside the seed item's subject bucket(s).
   *
   * This is the primary serendipity measure: a cross-domain click means the
   * visitor followed a recommendation that left their current subject area.
   *
   * @return int|null
   *   1 when the buckets differ, 0 when they overlap, NULL when unknown.
   */
  private function isCrossDomain(?string $seedBuckets, ?string $targetBucket): ?int {
    $target = trim((string) $targetBucket);
    $seed = trim((string) $seedBuckets);
    if ($target === '' || $seed === '') {
      return NULL;
    }
    $seedList = array_filter(array_map('trim', explode(',', $seed)));
    return in_array($target, $seedList, TRUE) ? 0 : 1;
  }

  /**
   * Look up rank/score/signals of an item inside a stored impression.
   */
  private function lookupResult($resultsJson, int $itemId): array {
    if ($itemId <= 0 || !is_string($resultsJson) || $resultsJson === '') {
      return [];
    }
    $rows = json_decode($resultsJson, TRUE);
    if (!is_array($rows)) {
      return [];
    }
    foreach ($rows as $row) {
      if (!is_array($row) || (int) ($row['id'] ?? 0) !== $itemId) {
        continue;
      }
      $signals = $row['signals'] ?? NULL;
      if (is_array($signals)) {
        $signals = implode(',', array_map('strval', array_keys($signals)));
      }
      return [
        'rank' => isset($row['rank']) ? (int) $row['rank'] : NULL,
        'score' => isset($row['score']) ? (float) $row['score'] : NULL,
        'signals' => is_string($signals) ? $signals : NULL,
        'bucket' => isset($row['bucket']) ? (string) $row['bucket'] : NULL,
      ];
    }
    return [];
  }

  /**
   * Rate/duplicate guard for incoming events.
   */
  private function acceptEvent(string $type, string $impressionKey): bool {
    try {
      if ($type === 'view') {
        $existing = (int) $this->conn->fetchOne(
          'SELECT COUNT(*) FROM ' . self::TABLE_EVENT . ' WHERE impression_key = ? AND event_type = ?',
          [$impressionKey, 'view']
        );
        return $existing === 0;
      }
      $clicks = (int) $this->conn->fetchOne(
        'SELECT COUNT(*) FROM ' . self::TABLE_EVENT . ' WHERE impression_key = ? AND event_type = ?',
        [$impressionKey, 'click']
      );
      return $clicks < self::MAX_CLICKS_PER_IMPRESSION;
    }
    catch (\Throwable $e) {
      return TRUE;
    }
  }

  /**
   * Write to the Omeka logger when one is available.
   */
  private function safeLog(string $message): void {
    if (!$this->logger) {
      return;
    }
    try {
      if (method_exists($this->logger, 'err')) {
        $this->logger->err($message);
      }
      elseif (method_exists($this->logger, 'error')) {
        $this->logger->error($message);
      }
    }
    catch (\Throwable $e) {
      // Never let logging break the request.
    }
  }

}
