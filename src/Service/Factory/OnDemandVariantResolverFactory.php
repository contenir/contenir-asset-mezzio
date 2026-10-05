<?php

declare(strict_types=1);

namespace Contenir\Asset\Mezzio\Service\Factory;

use Contenir\Asset\Mezzio\Container\Services;
use Contenir\Asset\Mezzio\Service\OnDemandVariantResolver;
use Contenir\Storage\StorageManager;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use UnexpectedValueException;

/**
 * Wires the resolver to the site-registered {@see StorageManager}. R2 sites must
 * register that service (e.g. via contenir/storage's StorageConfig); the
 * resolver is only constructed when the generation route is hit, so local sites
 * that never expose the route incur no dependency.
 */
final class OnDemandVariantResolverFactory
{
    /**
     * @throws ContainerExceptionInterface
     * @throws UnexpectedValueException When a dependency has the wrong type.
     */
    public function __invoke(ContainerInterface $container): OnDemandVariantResolver
    {
        return new OnDemandVariantResolver(Services::get($container, StorageManager::class));
    }
}
