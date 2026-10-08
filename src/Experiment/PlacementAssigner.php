<?php

/**
 * @file
 * Random assignment of visitors to one placement of the recommendations.
 */

declare(strict_types=1);

namespace SimilarItems\Experiment;

use Laminas\Authentication\AuthenticationServiceInterface;
use Omeka\Settings\Settings;

/**
 * Decides which Similar Items block a visitor sees during the placement trial.
 *
 * The trial compares three placements of the same recommendations: the list in
 * the right sidebar, the row below the viewer, and the floating button. All
 * three blocks are assigned to the item pages in the site's theme settings;
 * this class decides, per visitor, which one of them renders.
 *
 * The unit of assignment is the visitor, kept by a first-party cookie holding a
 * random key, so that someone coming back on another day sees the same
 * placement. Sessions are too short for that (30 minutes), and the existing
 * visitor key is rebuilt daily from address and browser.
 *
 * The trial has a window. Before it opens and after it closes, only the
 * fallback placement renders (the sidebar list, by default): the three blocks
 * can therefore be assigned in advance and the trial starts and stops on time
 * with nobody at the controls. With the trial switched off, every block that is
 * assigned renders, as without this class.
 */
class PlacementAssigner {

  public const SIDEBAR = 'sidebar';

  public const STRIP = 'strip';

  public const FLOATING = 'floating';

  public const PLACEMENTS = [self::SIDEBAR, self::STRIP, self::FLOATING];

  public const STATE_OFF = 'off';

  public const STATE_SCHEDULED = 'scheduled';

  public const STATE_ACTIVE = 'active';

  public const STATE_ENDED = 'ended';

  public const COOKIE = 'si_place';

  /**
   * Lifetime of the assignment cookie. Longer than any planned trial, so that
   * a visitor keeps one placement throughout.
   */
  private const COOKIE_DAYS = 60;

  /**
   * Query parameter a signed-in user can set to preview one placement.
   */
  public const PREVIEW_PARAM = 'si_placement';

  private Settings $settings;

  private ?AuthenticationServiceInterface $auth;

  /**
   * Visitor key for this request, once resolved. FALSE when there is none.
   *
   * @var string|false|null
   */
  private $unitKey = NULL;

  /**
   * Whether the key came from the request's cookie (rather than being minted).
   */
  private bool $unitFromCookie = FALSE;

  /**
   * Placement for this request, once resolved. FALSE means "no restriction".
   *
   * @var string|false|null
   */
  private $current = NULL;

  /**
   * Constructor.
   */
  public function __construct(Settings $settings, ?AuthenticationServiceInterface $auth = NULL) {
    $this->settings = $settings;
    $this->auth = $auth;
  }

  /**
   * Whether the trial is switched on (whatever its window).
   */
  public function isEnabled(): bool {
    return (int) ($this->settings->get('similaritems.placement_trial.enable') ?? 0) === 1;
  }

  /**
   * Placements taking part, in a fixed order.
   *
   * @return string[]
   *   Enabled placements.
   */
  public function getArms(): array {
    $arms = [];
    foreach (self::PLACEMENTS as $placement) {
      if ((int) ($this->settings->get('similaritems.placement_trial.use_' . $placement) ?? 1) === 1) {
        $arms[] = $placement;
      }
    }
    return $arms;
  }

  /**
   * Start of the window (unix time), 0 when it starts as soon as enabled.
   */
  public function getStart(): int {
    return max(0, (int) ($this->settings->get('similaritems.placement_trial.start_at') ?? 0));
  }

  /**
   * End of the window (unix time, exclusive), 0 when open-ended.
   */
  public function getEnd(): int {
    return max(0, (int) ($this->settings->get('similaritems.placement_trial.end_at') ?? 0));
  }

  /**
   * The placement shown outside the window.
   */
  public function getFallback(): string {
    $fallback = (string) ($this->settings->get('similaritems.placement_trial.fallback') ?? self::SIDEBAR);
    return in_array($fallback, self::PLACEMENTS, TRUE) ? $fallback : self::SIDEBAR;
  }

  /**
   * Where the trial stands at a given moment.
   *
   * @return string
   *   One of the STATE_* constants.
   */
  public function getState(?int $now = NULL): string {
    if (!$this->isEnabled() || count($this->getArms()) < 1) {
      return self::STATE_OFF;
    }
    $now = $now ?? time();
    $start = $this->getStart();
    $end = $this->getEnd();
    if ($start > 0 && $now < $start) {
      return self::STATE_SCHEDULED;
    }
    if ($end > 0 && $now >= $end) {
      return self::STATE_ENDED;
    }
    return self::STATE_ACTIVE;
  }

  /**
   * The placement that renders on this request.
   *
   * @return string|null
   *   A placement, or NULL when no restriction applies (trial off): every
   *   assigned block renders.
   */
  public function current(): ?string {
    if ($this->current === NULL) {
      $this->current = $this->resolveCurrent() ?? FALSE;
    }
    return $this->current === FALSE ? NULL : $this->current;
  }

  /**
   * Whether the block for a placement renders on this request.
   */
  public function shows(string $placement): bool {
    $current = $this->current();
    return $current === NULL || $current === $placement;
  }

  /**
   * The arm this visitor was assigned to, for the usage log.
   *
   * Only while the trial is running, and only for a visitor whose key came
   * with the request. A key minted now (cookies refused) would differ from the
   * one the page was rendered with, and the record would name an arm the
   * visitor never saw; the analysis then relies on the placement actually
   * rendered, which the log keeps separately.
   */
  public function assignedArm(): ?string {
    if ($this->getState() !== self::STATE_ACTIVE) {
      return NULL;
    }
    $key = $this->unitKey(FALSE);
    if ($key === NULL || !$this->unitFromCookie) {
      return NULL;
    }
    return $this->assign($key);
  }

  /**
   * A pseudonym of the visitor's assignment key, for clustering in analysis.
   *
   * Hashed with the trial seed so the stored value cannot be matched back to
   * the cookie. NULL in the same cases as assignedArm().
   */
  public function unitHash(): ?string {
    if ($this->assignedArm() === NULL) {
      return NULL;
    }
    return substr(hash('sha256', 'unit|' . $this->unitKey(FALSE) . '|' . $this->ensureSeed()), 0, 16);
  }

  /**
   * Deterministic assignment of a key to one enabled placement.
   */
  public function assign(string $key): string {
    $arms = $this->getArms();
    if (!$arms) {
      return $this->getFallback();
    }
    $hash = hash('sha256', $key . '|' . $this->ensureSeed());
    return $arms[hexdec(substr($hash, 0, 8)) % count($arms)];
  }

  /**
   * Seed for the assignment hash, created once and stored.
   *
   * Separate from the control-group trial's seed, so the two randomisations
   * are independent. Must stay fixed for the whole trial: changing it reassigns
   * every visitor.
   */
  public function ensureSeed(): string {
    $seed = (string) ($this->settings->get('similaritems.placement_trial.seed') ?? '');
    if ($seed !== '') {
      return $seed;
    }
    try {
      $seed = bin2hex(random_bytes(16));
    }
    catch (\Throwable $e) {
      $seed = hash('sha256', uniqid('siplace', TRUE) . microtime(TRUE));
    }
    try {
      $this->settings->set('similaritems.placement_trial.seed', $seed);
    }
    catch (\Throwable $e) {
      return '';
    }
    return $seed;
  }

  /**
   * Decide the placement for this request.
   */
  private function resolveCurrent(): ?string {
    $state = $this->getState();
    if ($state === self::STATE_OFF) {
      return NULL;
    }
    $preview = $this->preview();
    if ($preview !== NULL) {
      return $preview;
    }
    if ($state !== self::STATE_ACTIVE) {
      return $this->getFallback();
    }
    $key = $this->unitKey(TRUE);
    return $key === NULL ? $this->getFallback() : $this->assign($key);
  }

  /**
   * A placement requested for preview by a signed-in user.
   *
   * Lets staff check each placement on the live site. Signed-in visits are
   * not recorded in the usage log, so a preview never enters the data.
   */
  private function preview(): ?string {
    $wanted = isset($_GET[self::PREVIEW_PARAM]) ? (string) $_GET[self::PREVIEW_PARAM] : '';
    if ($wanted === '' || !in_array($wanted, self::PLACEMENTS, TRUE)) {
      return NULL;
    }
    try {
      if ($this->auth && $this->auth->hasIdentity()) {
        return $wanted;
      }
    }
    catch (\Throwable $e) {
      // No identity available: no preview.
    }
    return NULL;
  }

  /**
   * The visitor's assignment key: from the cookie, or minted and set.
   *
   * @param bool $mint
   *   Create (and set) a key when the request has none.
   */
  private function unitKey(bool $mint): ?string {
    if ($this->unitKey !== NULL && ($this->unitKey !== FALSE || !$mint)) {
      return $this->unitKey === FALSE ? NULL : $this->unitKey;
    }
    $raw = $_COOKIE[self::COOKIE] ?? '';
    if (is_string($raw) && preg_match('/^[a-f0-9]{32}$/', $raw)) {
      $this->unitKey = $raw;
      $this->unitFromCookie = TRUE;
      return $raw;
    }
    if (!$mint) {
      $this->unitKey = FALSE;
      return NULL;
    }
    try {
      $key = bin2hex(random_bytes(16));
    }
    catch (\Throwable $e) {
      $key = substr(hash('sha256', uniqid('siunit', TRUE) . microtime(TRUE)), 0, 32);
    }
    if (!headers_sent()) {
      @setcookie(self::COOKIE, $key, [
        'expires' => time() + self::COOKIE_DAYS * 86400,
        'path' => '/',
        'secure' => $this->isHttps(),
        'httponly' => TRUE,
        'samesite' => 'Lax',
      ]);
    }
    $_COOKIE[self::COOKIE] = $key;
    $this->unitKey = $key;
    $this->unitFromCookie = FALSE;
    return $key;
  }

  /**
   * Whether the current request is over HTTPS (directly or behind a proxy).
   */
  private function isHttps(): bool {
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
      return TRUE;
    }
    return strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
  }

}
