<?php

declare(strict_types=1);

namespace Contenir\Asset\Mezzio\View\Factory;

use Contenir\Asset\Mezzio\Container\Services;
use Contenir\Asset\Mezzio\Service\AssetUrlBuilder;
use Contenir\Asset\Mezzio\Service\ProfileProviderService;
use Contenir\Asset\Mezzio\View\StorageSources;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use UnexpectedValueException;

/**
 * @api
 */
final class StorageSourcesFactory
{
    /**
     * @throws ContainerExceptionInterface
     * @throws UnexpectedValueException When a dependency has the wrong type.
     */
    public function __invoke(ContainerInterface $container): StorageSources
    {
        return new StorageSources(
            Services::get($container, ProfileProviderService::class),
            Services::get($container, AssetUrlBuilder::class),
            Services::logger($container),
        );
    }
}
