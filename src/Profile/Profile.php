<?php

declare(strict_types=1);

namespace Contenir\Asset\Mezzio\Profile;

use Contenir\Storage\Variant;

/**
 * Immutable view of one storage profile from `settings.storage.profiles`, as the
 * front-end needs it: the HTML `sizes` attribute, the extra `<picture>` output
 * formats, and the responsive variants (the CMS preview variant excluded).
 */
final class Profile
{
    /**
     * @param list<string>  $formats  Extra output formats, e.g. ['avif', 'webp'].
     * @param list<Variant> $variants Responsive variants, preview excluded.
     */
    public function __construct(
        public readonly string $key,
        public readonly string $sizes,
        public readonly array $formats,
        public readonly array $variants,
    ) {}
}
