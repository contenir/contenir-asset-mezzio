<?php

declare(strict_types=1);

namespace Contenir\Asset\Mezzio\Tests\Trait;

use Contenir\Asset\Mezzio\ConfigProvider;
use Laminas\Diactoros\ConfigProvider as DiactorosConfigProvider;
use Laminas\ServiceManager\ServiceManager;
use Laminas\Stdlib\ArrayUtils;
use Mezzio\ConfigProvider as MezzioConfigProvider;
use Mezzio\Router\ConfigProvider as RouterConfigProvider;
use Mezzio\Router\FastRouteRouter\ConfigProvider as FastRouteConfigProvider;

/**
 * A fresh laminas-servicemanager container per test, configured the way a
 * Mezzio skeleton is: diactoros, mezzio, mezzio-router with FastRoute, and
 * this package's ConfigProvider, with $config merged last.
 */
trait MezzioContainerTrait
{
    /**
     * @param array<string, mixed> $config
     */
    private function mezzioContainer(array $config): ServiceManager
    {
        $merged = [];
        foreach ([
            (new DiactorosConfigProvider())(),
            (new MezzioConfigProvider())(),
            (new RouterConfigProvider())(),
            (new FastRouteConfigProvider())(),
            (new ConfigProvider())(),
            $config,
        ] as $provided) {
            $merged = ArrayUtils::merge($merged, $provided);
        }

        /** @var array<string, mixed> $dependencies */
        $dependencies = $merged['dependencies'];
        $container    = new ServiceManager($dependencies);
        $container->setService('config', $merged);

        return $container;
    }
}
