<?php

declare(strict_types=1);

namespace Contenir\Asset\Mezzio\View\Factory;

use Contenir\Asset\Mezzio\View\ConfigValues;
use Contenir\Asset\Mezzio\View\SiteAssets;
use Contenir\Asset\Mezzio\View\SiteAssetsOptions;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;

use function is_array;

/**
 * Builds SiteAssets from the `site_assets` config block. The inline CSS is
 * read under `site_assets.public_directory`, the application's public/ by
 * default, relative to the working directory, as Mezzio's is.
 *
 * @api
 */
final class SiteAssetsFactory
{
    /**
     * @throws ContainerExceptionInterface
     *
     * @mago-expect analysis:mixed-assignment The config service is untyped; its block is checked here.
     */
    public function __invoke(ContainerInterface $container): SiteAssets
    {
        $config = $container->get('config');
        $block  = is_array($config) && is_array($config['site_assets'] ?? null) ? $config['site_assets'] : [];

        return new SiteAssets(
            SiteAssetsOptions::fromArray($block),
            ConfigValues::text($block, 'public_directory', 'public'),
        );
    }
}
