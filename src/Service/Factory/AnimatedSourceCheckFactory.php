<?php

declare(strict_types=1);

namespace Contenir\Asset\Mezzio\Service\Factory;

use Contenir\Asset\Mezzio\Container\Services;
use Contenir\Asset\Mezzio\Service\AnimatedSourceCheck;
use Contenir\Asset\Mezzio\Service\AssetUrlBuilder;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use UnexpectedValueException;

final class AnimatedSourceCheckFactory
{
    /**
     * @throws ContainerExceptionInterface
     * @throws UnexpectedValueException When a dependency has the wrong type.
     */
    public function __invoke(ContainerInterface $container): AnimatedSourceCheck
    {
        return new AnimatedSourceCheck(
            Services::get($container, AssetUrlBuilder::class),
            Services::backendOption($container, 'root_path') ?? 'public',
            AssetUrlBuilder::BACKEND_LOCAL
                === (Services::backendOption($container, 'type') ?? AssetUrlBuilder::BACKEND_LOCAL),
        );
    }
}
