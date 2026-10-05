<?php

declare(strict_types=1);

namespace Contenir\Asset\Mezzio\View\Factory;

use Contenir\Asset\Mezzio\Container\Services;
use Contenir\Asset\Mezzio\Service\ProfileProviderService;
use Contenir\Asset\Mezzio\View\StorageSizes;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use UnexpectedValueException;

/**
 * @api
 */
final class StorageSizesFactory
{
    /**
     * @throws ContainerExceptionInterface
     * @throws UnexpectedValueException When a dependency has the wrong type.
     */
    public function __invoke(ContainerInterface $container): StorageSizes
    {
        return new StorageSizes(Services::get($container, ProfileProviderService::class));
    }
}
