<?php

declare(strict_types=1);

namespace Contenir\Asset\Mezzio\Handler\Factory;

use Contenir\Asset\Mezzio\Container\Services;
use Contenir\Asset\Mezzio\Handler\AssetVariantGenerateHandler;
use Contenir\Asset\Mezzio\Service\OnDemandVariantResolver;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use UnexpectedValueException;

/**
 * @api
 */
final class AssetVariantGenerateHandlerFactory
{
    /**
     * @throws ContainerExceptionInterface
     * @throws UnexpectedValueException When a dependency has the wrong type.
     */
    public function __invoke(ContainerInterface $container): AssetVariantGenerateHandler
    {
        return new AssetVariantGenerateHandler(
            Services::get($container, OnDemandVariantResolver::class),
            Services::get($container, ResponseFactoryInterface::class),
            Services::get($container, StreamFactoryInterface::class),
            Services::backendOption($container, 'generate_secret') ?? '',
        );
    }
}
