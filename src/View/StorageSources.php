<?php

declare(strict_types=1);

namespace Contenir\Asset\Mezzio\View;

use Contenir\Asset\Mezzio\Service\AnimatedSourceCheck;
use Contenir\Asset\Mezzio\Service\AssetUrlBuilder;
use Contenir\Asset\Mezzio\Service\ProfileProviderService;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

use function htmlspecialchars;
use function sprintf;

use const ENT_HTML5;
use const ENT_QUOTES;

/**
 * `<source>` elements for a `<picture>`, one per extra output format declared
 * on the profile (e.g. AVIF then WebP), each carrying the profile's `sizes`
 * attribute:
 *
 *   <picture>
 *     <?= $this->storageSources($asset->path, 'tile') ?>
 *     <img ... srcset="<?= $this->storageSrcSet($asset->path, 'tile') ?>"
 *          sizes="<?= $this->storageSizes('tile') ?>">
 *   </picture>
 *
 * Pass $lazy = true to emit `data-lazysrc-srcset` instead of `srcset`, so a
 * data-attribute lazy-loader can defer the avif/webp sources the same way it
 * defers the <img>; without it a live <source srcset> would fetch eagerly and
 * defeat lazy loading.
 *
 * Pass $sizes to give these sources the `sizes` the template gives its <img>
 * where the image's slot differs from the profile's usual one (half a gallery
 * row, say); the browser reads a <source>'s own sizes, so leaving the profile's
 * there would have it fetch for the wrong width. An empty $sizes leaves the
 * attribute out.
 *
 * Returns raw markup — asset paths are percent-encoded and the sizes value is
 * escaped. An unknown profile renders '' and logs a warning. An animated source
 * renders '' too: resizing would keep one frame, so it is served as itself.
 *
 * A plain invokable service: call it from any template engine.
 *
 * @api
 */
final readonly class StorageSources
{
    public function __construct(
        private ProfileProviderService $profiles,
        private AssetUrlBuilder $urls,
        private LoggerInterface $logger = new NullLogger(),
        private ?AnimatedSourceCheck $animation = null,
    ) {}

    /**
     * @param string|null $sizes The `sizes` for each source in place of the profile's
     *
     * @mago-expect lint:no-boolean-flag-parameter Published template API: `$lazy` switches the srcset attribute name.
     */
    public function __invoke(?string $path, string $profile, bool $lazy = false, ?string $sizes = null): string
    {
        if (null === $path || '' === $path) {
            return '';
        }

        $definition = $this->profiles->get($profile);
        if (null === $definition) {
            $this->logger->warning('StorageSources: unknown image profile "{profile}".', ['profile' => $profile]);

            return '';
        }
        if ([] === $definition->variants || true === $this->animation?->isAnimated($path)) {
            return '';
        }

        $srcsetAttr = $lazy ? 'data-lazysrc-srcset' : 'srcset';
        $sizes      ??= $definition->sizes;
        $sizesAttr  = '' === $sizes ? '' : sprintf(' sizes="%s"', htmlspecialchars($sizes, ENT_QUOTES | ENT_HTML5));

        $output = '';
        foreach ($definition->formats as $format) {
            $output .= sprintf(
                '<source type="image/%s" %s="%s"%s>',
                $format,
                $srcsetAttr,
                $this->urls->srcset($path, $definition->variants, $format),
                $sizesAttr,
            );
        }

        return $output;
    }
}
