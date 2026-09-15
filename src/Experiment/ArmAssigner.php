<?php

/**
 * @file
 * Randomised assignment of visitors to experiment arms.
 */

declare(strict_types=1);

namespace SimilarItems\Experiment;

use Omeka\Settings\Settings;

/**
 * Assigns each visitor session to a recommendation arm.
 *
 * The point of the control arms is that the usage log on its own can only say
 * which recommendation was chosen once recommendations are shown. It cannot say
 * whether showing them changes behaviour at all, nor whether the scoring engine
 * beats simply putting some items on the page. Two controls answer that:
 *
 * - "off"    : the block is hidden. Comparing it with "default" measures what
 *              the feature as a whole contributes to browsing.
 * - "random" : the same block with randomly drawn items. Comparing it with
 *              "default" isolates the scoring engine, holding the interface,
 *              the number of items and their positions constant. It also gives
 *              an empirical chance level for the click-through rate.
 *
 * Assignment is by session and deterministic: the arm is derived from the
 * session key and a stored seed, so it never changes mid-session, needs no
 * storage of its own, and can be recomputed during analysis.
 */
class ArmAssigner {

  /**
   * Normal, scored recommendations.
   */
  public const ARM_DEFAULT = 'default';

  /**
   * Same interface, randomly drawn items.
   */
  public const ARM_RANDOM = 'random';

  /**
   * Block hidden entirely.
   */
  public const ARM_OFF = 'off';

  /**
   * All arms, in reporting order.
   */
  public const ARMS = [self::ARM_DEFAULT, self::ARM_RANDOM, self::ARM_OFF];

  /**
   * Global settings.
   *
   * @var \Omeka\Settings\Settings
   */
  private Settings $settings;

  /**
   * Constructor.
   */
  public function __construct(Settings $settings) {
    $this->settings = $settings;
  }

  /**
   * Whether visitors are being split across arms at all.
   *
   * Off by default: until an operator turns it on, every visitor gets the
   * normal recommendations and nothing about the live site changes.
   */
  public function isEnabled(): bool {
    return (int) ($this->settings->get('similaritems.experiment.enable') ?? 0) === 1;
  }

  /**
   * Whether a control arm is in use.
   *
   * The two controls answer different questions and can be run separately.
   * Dropping "off" keeps the whole algorithm evaluation intact and makes the
   * trial both shorter and milder, because a visitor in the "random" arm still
   * gets a list of items rather than losing the feature.
   */
  public function isArmEnabled(string $arm): bool {
    if ($arm === self::ARM_DEFAULT) {
      return TRUE;
    }
    $key = ($arm === self::ARM_RANDOM)
      ? 'similaritems.experiment.use_random'
      : 'similaritems.experiment.use_off';
    return (int) ($this->settings->get($key) ?? 1) === 1;
  }

  /**
   * Assignment weights per arm, as whole numbers.
   *
   * A disabled arm always weighs zero, whatever its configured weight, so
   * turning one off never silently leaves visitors assigned to it.
   *
   * @return array<string,int>
   *   Arm name => weight. Weights are relative, not percentages.
   */
  public function getWeights(): array {
    $weights = [
      self::ARM_DEFAULT => (int) ($this->settings->get('similaritems.experiment.weight_default') ?? 80),
      self::ARM_RANDOM => (int) ($this->settings->get('similaritems.experiment.weight_random') ?? 10),
      self::ARM_OFF => (int) ($this->settings->get('similaritems.experiment.weight_off') ?? 10),
    ];
    foreach ($weights as $arm => $weight) {
      $weights[$arm] = $this->isArmEnabled($arm) ? max(0, $weight) : 0;
    }
    return $weights;
  }

  /**
   * Effective share of visitors per arm, as percentages.
   *
   * What the operator actually gets after disabled arms are zeroed, which is
   * not always what the weight fields suggest.
   *
   * @return array<string,float>
   *   Arm name => percentage. Empty when the trial is not running.
   */
  public function getAllocation(): array {
    if (!$this->isEnabled()) {
      return [];
    }
    $weights = $this->getWeights();
    $total = array_sum($weights);
    if ($total <= 0) {
      return [];
    }
    $out = [];
    foreach ($weights as $arm => $weight) {
      if ($weight > 0) {
        $out[$arm] = 100 * $weight / $total;
      }
    }
    return $out;
  }

  /**
   * The arm this session belongs to.
   *
   * @param string|null $sessionKey
   *   Pseudonymous session key. Without one there is nothing stable to assign
   *   on, so the visitor gets the normal experience.
   *
   * @return string
   *   One of the ARM_* constants.
   */
  public function assign(?string $sessionKey): string {
    if (!$this->isEnabled() || $sessionKey === NULL || $sessionKey === '') {
      return self::ARM_DEFAULT;
    }
    $weights = $this->getWeights();
    $total = array_sum($weights);
    if ($total <= 0) {
      return self::ARM_DEFAULT;
    }
    // A seed separate from the hashing salt, so that re-running the experiment
    // can reshuffle assignments without touching the pseudonymisation.
    $hash = hash('sha256', $sessionKey . '|' . $this->getSeed());
    $point = hexdec(substr($hash, 0, 8)) % $total;
    $cursor = 0;
    foreach ($weights as $arm => $weight) {
      $cursor += $weight;
      if ($point < $cursor) {
        return $arm;
      }
    }
    return self::ARM_DEFAULT;
  }

  /**
   * Whether the block should be hidden for this session.
   */
  public function isHidden(?string $sessionKey): bool {
    return $this->assign($sessionKey) === self::ARM_OFF;
  }

  /**
   * Seed for the assignment hash, created once and stored.
   *
   * Must stay fixed for the whole trial: changing it reassigns every visitor
   * and splits the data into two incomparable halves.
   *
   * @return string
   *   The seed, or an empty string when it could not be stored.
   */
  public function ensureSeed(): string {
    $seed = (string) ($this->settings->get('similaritems.experiment.seed') ?? '');
    if ($seed !== '') {
      return $seed;
    }
    try {
      $seed = bin2hex(random_bytes(16));
    }
    catch (\Throwable $e) {
      $seed = hash('sha256', uniqid('siexp', TRUE) . microtime(TRUE));
    }
    try {
      $this->settings->set('similaritems.experiment.seed', $seed);
    }
    catch (\Throwable $e) {
      return '';
    }
    return $seed;
  }

  /**
   * Seed accessor.
   */
  private function getSeed(): string {
    return $this->ensureSeed();
  }

}
