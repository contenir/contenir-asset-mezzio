<?php

declare(strict_types=1);

namespace Contenir\Asset\Mezzio\View;

use Contenir\Asset\Mezzio\Service\AssetUrlBuilder;
use Contenir\Asset\Mezzio\Service\ProfileProviderService;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

use function sprintf;

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
 * Returns raw markup — asset paths are percent-encoded and the sizes value is
 * config. An unknown profile renders '' and logs a warning.
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
    ) {}

    /**
     * @mago-expect lint:no-boolean-flag-parameter Published template API: `$lazy` switches the srcset attribute name.
     */
    public function __invoke(?string $path, string $profile, bool $lazy = false): string
    {
        if (null === $path || '' === $path) {
            return '';
        }

        $definition = $this->profiles->get($profile);
        if (null === $definition) {
            $this->logger->warning('StorageSources: unknown image profile "{profile}".', ['profile' => $profile]);

            return '';
        }
        if ([] === $definition->variants) {
            return '';
        }

        $srcsetAttr = $lazy ? 'data-lazysrc-srcset' : 'srcset';
        $sizesAttr  = '' === $definition->sizes ? '' : sprintf(' sizes="%s"', $definition->sizes);

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
