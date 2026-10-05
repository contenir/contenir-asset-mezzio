<?php

declare(strict_types=1);

namespace Contenir\Asset\Mezzio\View;

use Contenir\Asset\Mezzio\Service\AssetUrlBuilder;
use Contenir\Asset\Mezzio\Service\ProfileProviderService;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * A single asset URL — the original, or a specific named variant (optionally
 * in a given format):
 *
 *   <a href="<?= $this->storageUrl($asset->path) ?>">original</a>
 *   <img src="<?= $this->storageUrl($asset->path, 'tile-640') ?>">
 *
 * Returns the raw URL — escaping is the output context's job. An unknown
 * variant logs a warning but still returns the URL it would have built, so
 * URL construction stays deterministic.
 *
 * A plain invokable service: call it from any template engine.
 *
 * @api
 */
final readonly class StorageUrl
{
    public function __construct(
        private ProfileProviderService $profiles,
        private AssetUrlBuilder $urls,
        private LoggerInterface $logger = new NullLogger(),
    ) {}

    public function __invoke(?string $path, ?string $variant = null, ?string $format = null): string
    {
        if (null === $path || '' === $path) {
            return '';
        }

        if (null === $variant || '' === $variant) {
            return $this->urls->originalUrl($path);
        }

        if (null === $this->profiles->variant($variant)) {
            $this->logger->warning('StorageUrl: unknown variant "{variant}".', ['variant' => $variant]);
        }

        return $this->urls->variantUrl($path, $variant, $format);
    }
}
