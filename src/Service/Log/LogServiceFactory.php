<?php

/**
 * @file
 * Factory for the SimilarItems usage log service.
 */

declare(strict_types=1);

namespace SimilarItems\Service\Log;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;
use SimilarItems\Log\LogService;

/**
 * Builds the LogService with the Omeka connection, settings and logger.
 */
class LogServiceFactory implements FactoryInterface {

  /**
   * {@inheritDoc}
   */
  public function __invoke(ContainerInterface $container, $requestedName, ?array $options = NULL): LogService {
    $conn = NULL;
    if ($container->has('Omeka\Connection')) {
      try {
        $conn = $container->get('Omeka\Connection');
      }
      catch (\Throwable $e) {
        $conn = NULL;
      }
    }
    $logger = NULL;
    if ($container->has('Omeka\Logger')) {
      try {
        $logger = $container->get('Omeka\Logger');
      }
      catch (\Throwable $e) {
        $logger = NULL;
      }
    }
    return new LogService($conn, $container->get('Omeka\Settings'), $logger);
  }

}
