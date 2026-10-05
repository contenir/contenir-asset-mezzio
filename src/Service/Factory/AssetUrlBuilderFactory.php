<?php

declare(strict_types=1);

namespace Contenir\Asset\Mezzio\Service\Factory;

use Contenir\Asset\Mezzio\Container\Services;
use Contenir\Asset\Mezzio\Service\AssetUrlBuilder;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use UnexpectedValueException;

final class AssetUrlBuilderFactory
{
    /**
     * @throws ContainerExceptionInterface
     * @throws UnexpectedValueException When a dependency has the wrong type.
     */
    public function __invoke(ContainerInterface $container): AssetUrlBuilder
    {
        $type = Services::backendOption($container, 'type') ?? AssetUrlBuilder::BACKEND_LOCAL;

        /**
         * Local serves variants under the web root (public_path); object stores
         * serve sibling objects from the bucket's public CDN base. `publicUrl` is
         * the storage layer's canonical key; `public_base_url` is kept as an alias.
         */
        $publicBase = AssetUrlBuilder::BACKEND_LOCAL === $type
            ? Services::backendOption($container, 'public_path')
            : Services::backendOption($container, 'public_base_url') ?? Services::backendOption(
                $container,
                'publicUrl',
            );

        return new AssetUrlBuilder($publicBase ?? '', $type);
    }
}
