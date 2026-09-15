<?php

/**
 * @file
 * Public endpoint that receives SimilarItems usage events from the browser.
 */

declare(strict_types=1);

namespace SimilarItems\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\JsonModel;
use SimilarItems\Log\LogService;

/**
 * Collects view/click events emitted by the recommendation block.
 */
class EventController extends AbstractActionController {

  /**
   * Maximum accepted request body size, in bytes.
   */
  private const MAX_BODY_BYTES = 4096;

  /**
   * Usage log service.
   *
   * @var \SimilarItems\Log\LogService
   */
  private LogService $log;

  /**
   * Constructor.
   */
  public function __construct(LogService $log) {
    $this->log = $log;
  }

  /**
   * Accept a single event, sent by fetch() or navigator.sendBeacon().
   */
  public function collectAction() {
    $response = $this->getResponse();
    // Never let a proxy cache or replay this.
    $headers = $response->getHeaders();
    $headers->addHeaderLine('Cache-Control', 'no-store, private');

    $request = $this->getRequest();
    $isPost = method_exists($request, 'isPost')
      ? $request->isPost()
      : (strtoupper((string) $request->getMethod()) === 'POST');
    if (!$isPost) {
      $response->setStatusCode(405);
      return new JsonModel(['ok' => FALSE]);
    }

    if (!$this->log->isEnabled()) {
      // Logging is off: answer normally so the client stays quiet.
      return new JsonModel(['ok' => FALSE]);
    }

    $payload = $this->readPayload();
    if ($payload === NULL) {
      $response->setStatusCode(400);
      return new JsonModel(['ok' => FALSE]);
    }

    // Attach the current user only when the operator opted in; LogService
    // drops it otherwise.
    try {
      $user = $this->identity();
      if ($user && method_exists($user, 'getId')) {
        $payload['user_id'] = (int) $user->getId();
      }
    }
    catch (\Throwable $e) {
      // Anonymous visitor.
    }

    $ok = $this->log->recordEvent($payload);
    return new JsonModel(['ok' => $ok]);
  }

  /**
   * Decode the request body.
   *
   * sendBeacon sends a Blob with a text/plain content type, so the body is
   * parsed as JSON regardless of the declared type, with a form-encoded
   * fallback.
   *
   * @return array|null
   *   Decoded payload, or NULL when the body is unusable.
   */
  private function readPayload(): ?array {
    $raw = (string) $this->getRequest()->getContent();
    if ($raw === '' || strlen($raw) > self::MAX_BODY_BYTES) {
      return NULL;
    }
    $decoded = json_decode($raw, TRUE);
    if (is_array($decoded)) {
      return $decoded;
    }
    $parsed = [];
    parse_str($raw, $parsed);
    return $parsed ? $parsed : NULL;
  }

}
