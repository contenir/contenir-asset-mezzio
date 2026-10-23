<?php

declare(strict_types=1);

namespace Contenir\Asset\Mezzio\Tests\TestAsset\Image;

use function str_repeat;

/**
 * Builds the bytes of a 1x1 GIF with any number of frames, so tests need no
 * GD extension and no binary fixture. ImageMagick decodes them.
 */
final class GifFactory
{
    /** Signature, logical screen descriptor and a 2-colour global table. */
    private const string HEADER = "GIF89a\x01\x00\x01\x00\x80\x00\x00\x00\x00\x00\xFF\xFF\xFF";

    /** The NETSCAPE2.0 application extension an animation loops by. */
    private const string LOOP = "\x21\xFF\x0BNETSCAPE2.0\x03\x01\x00\x00\x00";

    /** A graphic control extension with a 100ms delay, then one 1x1 frame. */
    private const string FRAME = "\x21\xF9\x04\x00\x0A\x00\x00\x00\x2C\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02\x44\x01\x00";

    private const string TRAILER = "\x3B";

    public static function bytes(int $frames = 1): string
    {
        return self::HEADER . ($frames > 1 ? self::LOOP : '') . str_repeat(self::FRAME, $frames) . self::TRAILER;
    }
}
