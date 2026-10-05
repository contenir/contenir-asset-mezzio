<?php

declare(strict_types=1);

namespace Contenir\Asset\Mezzio\Container;

use Contenir\Storage\Config\StorageConfig;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use UnexpectedValueException;

use function get_debug_type;
use function is_array;
use function is_string;
use function sprintf;

/**
 * Reads typed services and the `storage` config block from the container, so
 * misconfiguration fails with a clear message rather than a TypeError deep in
 * construction.
 *
 * @internal
 */
final class Services
{
    /**
     * A non-empty string option of the primary storage backend
     * ({@see StorageConfig::primaryBackendConfig()}), or null.
     *
     * @throws ContainerExceptionInterface
     *
     * @mago-expect analysis:mixed-assignment Configuration is untyped input; it is validated here.
     */
    public static function backendOption(ContainerInterface $container, string $key): ?string
    {
        $value = StorageConfig::primaryBackendConfig(self::storageConfig($container))[$key] ?? null;

        return is_string($value) && '' !== $value ? $value : null;
    }

    /**
     * @template S of object
     *
     * @param class-string<S> $type
     *
     * @return S
     *
     * @throws ContainerExceptionInterface
     * @throws UnexpectedValueException When the service is not a $type.
     *
     * @mago-expect analysis:mixed-assignment Container services are untyped; the type is checked here.
     */
    public static function get(ContainerInterface $container, string $type): object
    {
        $service = $container->get($type);
        if (! $service instanceof $type) {
            throw new UnexpectedValueException(sprintf(
                'Service "%s" must be an instance of %s, %s given.',
                $type,
                $type,
                get_debug_type($service),
            ));
        }

        return $service;
    }

    /**
     * The application's PSR-3 logger, registered as {@see LoggerInterface},
     * or a {@see NullLogger} when it has none.
     *
     * @throws ContainerExceptionInterface
     * @throws UnexpectedValueException When the logger service is not a LoggerInterface.
     */
    public static function logger(ContainerInterface $container): LoggerInterface
    {
        return $container->has(LoggerInterface::class)
            ? self::get($container, LoggerInterface::class)
            : new NullLogger();
    }

    /**
     * The application's `storage` config block, or null when it has none.
     *
     * @return array<string, mixed>|null
     *
     * @throws ContainerExceptionInterface
     *
     * @mago-expect analysis:mixed-assignment Configuration is untyped input; it is validated here.
     */
    public static function storageConfig(ContainerInterface $container): ?array
    {
        $config  = $container->get('config');
        $storage = is_array($config) ? $config['storage'] ?? null : null;
        if (! is_array($storage)) {
            return null;
        }

        /** @var array<string, mixed> $storage Config keys are strings. */
        return $storage;
    }

    /**
     * The `storage.variants` declarations, or an empty array.
     *
     * @return array<string, mixed>
     *
     * @throws ContainerExceptionInterface
     *
     * @mago-expect analysis:mixed-assignment Configuration is untyped input; it is validated here.
     */
    public static function storageVariants(ContainerInterface $container): array
    {
        $variants = self::storageConfig($container)['variants'] ?? null;
        if (! is_array($variants)) {
            return [];
        }

        /** @var array<string, mixed> $variants Variant names are strings. */
        return $variants;
    }
}
