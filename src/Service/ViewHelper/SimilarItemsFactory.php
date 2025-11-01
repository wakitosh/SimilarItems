<?php

declare(strict_types=1);

namespace SimilarItems\Service\ViewHelper;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;
use SimilarItems\View\Helper\SimilarItems as SimilarItemsHelper;

/**
 * Factory for the SimilarItems view helper.
 */
class SimilarItemsFactory implements FactoryInterface {

  /**
   * {@inheritDoc}
   */
  public function __invoke(ContainerInterface $container, $requestedName, ?array $options = NULL): SimilarItemsHelper {
    $settings = $container->get('Omeka\Settings');
    // Omeka logger implements PSR-3. Optional but useful for debug.
    /** @var \Psr\Log\LoggerInterface $logger */
    $logger = $container->get('Omeka\Logger');
    return new SimilarItemsHelper($settings, $logger);
  }

}
