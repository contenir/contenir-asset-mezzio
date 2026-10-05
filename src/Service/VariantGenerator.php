<?php

declare(strict_types=1);

namespace Contenir\Asset\Mezzio\Service;

use Contenir\Asset\Mezzio\Security\PathGuard;
use Contenir\Storage\Image\ImageResizerInterface;
use Contenir\Storage\Variant;
use Throwable;

use function is_file;
use function pathinfo;
use function rtrim;
use function sprintf;
use function strtolower;
use function strtoupper;
use function trim;

use const PATHINFO_EXTENSION;
use const PATHINFO_FILENAME;

/**
 * Resolves a requested keyed variant to a concrete file, generating it on demand
 * via an {@see ImageResizerInterface} when missing.
 *
 * The variant definition (width, height, fit, quality) is looked up from
 * {@see ProfileProviderService} by the variant name carried in the URL; the
 * output format comes from the requested filename's extension, falling back to
 * the source format when that format cannot be produced.
 */
final class VariantGenerator
{
    private const string VARIANT_DIR = '_variant';

    /** @var list<string> */
    private const array SOURCE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif'];

    private string $assetRoot;

    public function __construct(
        private ImageResizerInterface $resizer,
        private ProfileProviderService $profiles,
        string $rootPath,
    ) {
        $this->assetRoot = rtrim($rootPath, characters: '/');
    }

    /**
     * The path of the variant file for `<folder>/_variant/<name>/<filename>`,
     * generating it when missing, or null when the folder or filename is
     * unsafe, the variant is unknown, no source exists, or no format can be
     * produced.
     *
     * $folder, $name and $filename are the decoded request values. They pass
     * {@see PathGuard} before anything touches the filesystem: a `..` segment,
     * a null byte, a backslash, a leftover encoded dot or separator, or a
     * separator in the name or filename makes the request a miss.
     */
    public function generate(string $folder, string $name, string $filename): ?string
    {
        if (
            ! PathGuard::isSafePath($folder)
            || ! PathGuard::isSafeSegment($name)
            || ! PathGuard::isSafeSegment($filename)
        ) {
            return null;
        }

        $variant = $this->profiles->variant($name);
        if (null === $variant) {
            return null;
        }

        $folder = trim($folder, characters: '/');

        $source = $this->resolveSource($folder, $filename);
        if (null === $source) {
            return null;
        }

        $variantDir   = sprintf('%s/asset/%s/%s/%s', $this->assetRoot, $folder, self::VARIANT_DIR, $name);
        $base         = pathinfo($filename, PATHINFO_FILENAME);
        $requestedExt = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $sourceExt    = strtolower(pathinfo($source, PATHINFO_EXTENSION));

        $dest = "{$variantDir}/{$base}.{$requestedExt}";
        if (is_file($dest) || $this->materialise($source, $dest, $variant)) {
            return $dest;
        }

        /**
         * Requested format unproducible (e.g. no AVIF encoder) — fall back to
         * the source format.
         */
        $fallback = "{$variantDir}/{$base}.{$sourceExt}";
        if (is_file($fallback) || $this->materialise($source, $fallback, $variant)) {
            return $fallback;
        }

        return null;
    }

    private function materialise(string $source, string $dest, Variant $variant): bool
    {
        try {
            $this->resizer->resize($source, $dest, $variant->width, $variant->height, $variant->fit, $variant->quality);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Resolve the source original on disk by basename within <folder>, ignoring
     * the requested variant extension (the URL carries the target format).
     */
    private function resolveSource(string $folder, string $filename): ?string
    {
        $dir  = sprintf('%s/asset/%s', $this->assetRoot, $folder);
        $base = pathinfo($filename, PATHINFO_FILENAME);

        $exact = "{$dir}/{$filename}";
        if (is_file($exact)) {
            return $exact;
        }

        foreach (self::SOURCE_EXTENSIONS as $ext) {
            foreach ([$ext, strtoupper($ext)] as $candidateExt) {
                $candidate = "{$dir}/{$base}.{$candidateExt}";
                if (is_file($candidate)) {
                    return $candidate;
                }
            }
        }

        return null;
    }
}
