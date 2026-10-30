<?php

declare(strict_types=1);

namespace Contenir\Asset\Mezzio\View;

use function array_key_exists;
use function is_array;
use function is_bool;
use function is_scalar;
use function is_string;

/**
 * Reads typed values from an untyped config block, leaving out values of the wrong type.
 *
 * @internal
 *
 * @mago-expect lint:cyclomatic-complexity One type check per kind of value; each method is a single check.
 */
final class ConfigValues
{
    /**
     * @param array<array-key, mixed> $config
     */
    public static function flag(array $config, string $key): bool
    {
        return is_bool($config[$key] ?? null) && $config[$key];
    }

    /**
     * A list of attribute maps, each with an `href`.
     *
     * @param array<array-key, mixed> $config
     *
     * @return list<array<string, string>>
     *
     * @mago-expect analysis:mixed-assignment Config values are untyped; each is checked here.
     */
    public static function links(array $config, string $key): array
    {
        $value = $config[$key] ?? null;
        $links = [];
        foreach (is_array($value) ? $value : [] as $item) {
            $attributes = self::map(['link' => $item], 'link');
            if (! array_key_exists('href', $attributes)) {
                continue;
            }

            $links[] = $attributes;
        }

        return $links;
    }

    /**
     * @param array<array-key, mixed> $config
     *
     * @return array<string, string>
     *
     * @mago-expect analysis:mixed-assignment Config values are untyped; each is checked here.
     */
    public static function map(array $config, string $key): array
    {
        $value = $config[$key] ?? null;
        $map   = [];
        foreach (is_array($value) ? $value : [] as $name => $item) {
            if (! is_string($name) || ! is_string($item)) {
                continue;
            }

            $map[$name] = $item;
        }

        return $map;
    }

    /**
     * A string or a list of strings, as a list; empty strings are left out.
     *
     * @param array<array-key, mixed> $config
     *
     * @return list<string>
     *
     * @mago-expect analysis:mixed-assignment Config values are untyped; each is checked here.
     */
    public static function strings(array $config, string $key): array
    {
        $value = $config[$key] ?? null;
        $items = is_array($value) ? $value : [$value];
        $list  = [];
        foreach ($items as $item) {
            if (! is_string($item) || '' === $item) {
                continue;
            }

            $list[] = $item;
        }

        return $list;
    }

    /**
     * The value as text, or $default for none, an empty one or one that isn't scalar.
     *
     * @param array<array-key, mixed> $config
     */
    public static function text(array $config, string $key, string $default = ''): string
    {
        $text = is_scalar($config[$key] ?? null) ? (string) $config[$key] : '';

        return '' === $text ? $default : $text;
    }
}
