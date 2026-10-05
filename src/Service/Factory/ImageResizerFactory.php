<?php

declare(strict_types=1);

namespace Contenir\Asset\Mezzio\Service\Factory;

use Contenir\Asset\Mezzio\Container\Services;
use Contenir\Storage\Image\ImageResizer;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use UnexpectedValueException;

/**
 * Builds the contenir/storage ImageResizer. With no `binary` configured on the
 * primary backend it auto-discovers magick/convert from PATH.
 */
final class ImageResizerFactory
{
    /**
     * @throws ContainerExceptionInterface
     * @throws UnexpectedValueException When a dependency has the wrong type.
     */
    public function __invoke(ContainerInterface $container): ImageResizer
    {
        return new ImageResizer(Services::backendOption($container, 'binary'));
    }
}
