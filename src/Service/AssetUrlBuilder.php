<?php

declare(strict_types=1);

namespace Contenir\Asset\Mezzio\Service;

use Contenir\Storage\Variant;

use function array_map;
use function basename;
use function dirname;
use function explode;
use function implode;
use function ltrim;
use function preg_replace;
use function rtrim;
use function sprintf;
use function str_starts_with;
use function strlen;
use function strrpos;
use function substr;

/**
 * Builds public URLs for stored assets and their keyed variants.
 *
 * The URL scheme depends on the storage backend:
 *
 * - local — a variant of `<dir>/file.jpg` lives at `<dir>/_variant/<name>/file.<fmt>`,
 *   served on demand by {@see \Contenir\Asset\Mezzio\Handler\AssetVariantHandler}.
 * - r2/s3 — a variant lives at the sibling key `<dir>/file__<name>.<fmt>` on the
 *   bucket's public CDN base (admin4's sibling-key convention), generated at
 *   upload, by backfill, or on demand at the edge.
 *
 * Path segments are percent-encoded under both schemes. CMS filenames routinely
 * carry spaces and parentheses, and an unencoded space breaks the srcset
 * tokeniser outright -- it reads the text after the space as the candidate's
 * descriptor and discards the whole attribute. The local variant handler
 * decodes folder and filename once on the way back in, so encoding is safe
 * for both. Escaping remains the output context's job.
 */
final class AssetUrlBuilder
{
    public const string BACKEND_LOCAL = 'local';

    private const string VARIANT_DIR = '_variant';

    private string $publicBase;
    private bool $siblingScheme;

    public function __construct(string $publicBase, string $backend = self::BACKEND_LOCAL)
    {
        $this->publicBase    = rtrim($publicBase, characters: '/');
        $this->siblingScheme = self::BACKEND_LOCAL !== $backend;
    }

    public function originalUrl(string $path): string
    {
        return $this->absoluteEncoded($this->key($path));
    }

    /**
     * @param list<Variant> $variants
     */
    public function srcset(string $path, array $variants, ?string $format = null): string
    {
        $entries = [];
        foreach ($variants as $variant) {
            $entries[] = "{$this->variantUrl($path, $variant->name, $format)} {$variant->width}w";
        }

        return implode(', ', $entries);
    }

    public function variantUrl(string $path, string $name, ?string $format = null): string
    {
        $key    = $this->key($path);
        $format = '' === $format ? null : $format;

        if ($this->siblingScheme) {
            return $this->absoluteEncoded($this->siblingKey($key, $name, $format));
        }

        $dir  = dirname($key);
        $file = basename($key);
        if (null !== $format) {
            $file =
                preg_replace(
                    pattern: '/\.[^.\/]+$/',
                    replacement: ".{$format}",
                    subject: $file,
                ) ?? $file;
        }

        $variantKey = '.' === $dir
            ? sprintf('%s/%s/%s', self::VARIANT_DIR, $name, $file)
            : sprintf('%s/%s/%s/%s', $dir, self::VARIANT_DIR, $name, $file);

        return $this->absoluteEncoded($variantKey);
    }

    private function absoluteEncoded(string $key): string
    {
        $segments = explode(
            separator: '/',
            string: ltrim($key, characters: '/'),
        );

        return $this->publicBase . '/' . implode('/', array_map(rawurlencode(...), $segments));
    }

    /**
     * For the local scheme, strip the public-path prefix so the key is relative
     * to the asset root — otherwise the URL doubles up to
     * /asset/library/asset/library/... For the sibling scheme the stored path is
     * the bucket object key and is used verbatim under the CDN base.
     */
    private function key(string $path): string
    {
        $path   = ltrim($path, characters: '/');
        $prefix = ltrim($this->publicBase, characters: '/') . '/';
        if ($this->siblingScheme || '/' === $prefix || ! str_starts_with($path, $prefix)) {
            return $path;
        }

        return substr($path, strlen($prefix));
    }

    /**
     * Sibling-object key for the s3/r2 scheme: `<base>__<name>.<format>`, where
     * `<base>` is the key with its extension stripped. A null $format keeps the
     * source extension (the <img> fallback); otherwise the extension is swapped
     * for the requested format (the avif/webp <source>s).
     */
    private function siblingKey(string $key, string $name, ?string $format): string
    {
        $dot  = strrpos($key, needle: '.');
        $base = false === $dot ? $key : substr($key, offset: 0, length: $dot);
        $ext  = match (true) {
            null !== $format => ".{$format}",
            false !== $dot => substr($key, $dot),
            default => '',
        };

        return "{$base}__{$name}{$ext}";
    }
}
