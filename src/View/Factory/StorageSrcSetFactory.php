<?php

declare(strict_types=1);

namespace Contenir\Asset\Mezzio\View\Factory;

use Contenir\Asset\Mezzio\Container\Services;
use Contenir\Asset\Mezzio\Service\AssetUrlBuilder;
use Contenir\Asset\Mezzio\Service\ProfileProviderService;
use Contenir\Asset\Mezzio\View\StorageSrcSet;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use UnexpectedValueException;

/**
 * @api
 */
final class StorageSrcSetFactory
{
    /**
     * @throws ContainerExceptionInterface
     * @throws UnexpectedValueException When a dependency has the wrong type.
     */
    public function __invoke(ContainerInterface $container): StorageSrcSet
    {
        return new StorageSrcSet(
            Services::get($container, ProfileProviderService::class),
            Services::get($container, AssetUrlBuilder::class),
            Services::logger($container),
        );
    }
}
