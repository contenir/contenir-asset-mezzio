<?php

declare(strict_types=1);

namespace Contenir\Asset\Mezzio\Handler\Factory;

use Contenir\Asset\Mezzio\Container\Services;
use Contenir\Asset\Mezzio\Handler\AssetVariantHandler;
use Contenir\Asset\Mezzio\Service\VariantGenerator;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use UnexpectedValueException;

/**
 * @api
 */
final class AssetVariantHandlerFactory
{
    /**
     * @throws ContainerExceptionInterface
     * @throws UnexpectedValueException When a dependency has the wrong type.
     */
    public function __invoke(ContainerInterface $container): AssetVariantHandler
    {
        return new AssetVariantHandler(
            Services::get($container, VariantGenerator::class),
            Services::get($container, ResponseFactoryInterface::class),
            Services::get($container, StreamFactoryInterface::class),
        );
    }
}
