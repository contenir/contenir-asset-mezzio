<?php

declare(strict_types=1);

namespace Contenir\Asset\Mezzio\Service;

use Contenir\Storage\Image\AnimationDetector;

use function pathinfo;
use function strtolower;

use const PATHINFO_EXTENSION;

/**
 * A source a variant request is answered with as it is, never resized
 *
 * - An animation: resizing would keep one frame. The helpers offer no variants of it, so this
 *   answers URLs from before.
 * - A vector: it is already every size, and ImageMagick writes an SVG it resizes as a broken one.
 */
final class UnresizedSource
{
    public static function is(string $source): bool
    {
        return (
            'svg' === strtolower(pathinfo($source, PATHINFO_EXTENSION))
                || AnimationDetector::isAnimatedFile($source)
        );
    }
}
