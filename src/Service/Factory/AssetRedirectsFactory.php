<?php

declare(strict_types=1);

namespace Contenir\Asset\Mezzio\Service\Factory;

use Contenir\Asset\Mezzio\Container\Services;
use Contenir\Asset\Mezzio\Service\AssetRedirects;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;

use function is_string;

/**
 * The redirects of files the CMS moved, from the JSON file `storage.redirects` names, else the
 * one the CMS writes, `data/assets/redirects.json` below the site.
 *
 * @api
 */
final class AssetRedirectsFactory
{
    public const string DEFAULT_FILE = 'data/assets/redirects.json';

    /**
     * @throws ContainerExceptionInterface
     *
     * @mago-expect analysis:mixed-assignment Configuration is untyped input; only text is kept.
     */
    public function __invoke(ContainerInterface $container): AssetRedirects
    {
        $storage = $container->has('config') ? Services::storageConfig($container) : null;
        $file    = $storage['redirects'] ?? null;

        return new AssetRedirects(is_string($file) && '' !== $file ? $file : self::DEFAULT_FILE);
    }
}
