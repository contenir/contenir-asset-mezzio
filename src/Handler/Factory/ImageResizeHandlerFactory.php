<?php

declare(strict_types=1);

namespace Contenir\Asset\Mezzio\Handler\Factory;

use Contenir\Asset\Image\LegacyResizer;
use Contenir\Asset\Mezzio\Container\Services;
use Contenir\Asset\Mezzio\Handler\ImageResizeHandler;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use UnexpectedValueException;

/**
 * @api
 */
final class ImageResizeHandlerFactory
{
    /**
     * @throws ContainerExceptionInterface
     * @throws UnexpectedValueException When a dependency has the wrong type.
     *
     * @mago-expect analysis:deprecated-class Builds the deprecated legacy handler for sites still using it.
     */
    public function __invoke(ContainerInterface $container): ImageResizeHandler
    {
        return new ImageResizeHandler(
            Services::get($container, LegacyResizer::class),
            Services::get($container, ResponseFactoryInterface::class),
            Services::get($container, StreamFactoryInterface::class),
        );
    }
}
