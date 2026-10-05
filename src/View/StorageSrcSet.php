<?php

declare(strict_types=1);

namespace Contenir\Asset\Mezzio\View;

use Contenir\Asset\Mezzio\Service\AssetUrlBuilder;
use Contenir\Asset\Mezzio\Service\ProfileProviderService;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * A responsive srcset for a stored asset over a named profile's variant
 * ladder, in the source image format:
 *
 *   data-lazysrc-srcset="<?= $this->storageSrcSet($asset->path, 'tile') ?>"
 *
 * Returns the raw value — escaping is the output context's job. An unknown
 * profile renders '' and logs a warning.
 *
 * A plain invokable service: call it from any template engine.
 *
 * @api
 */
final readonly class StorageSrcSet
{
    public function __construct(
        private ProfileProviderService $profiles,
        private AssetUrlBuilder $urls,
        private LoggerInterface $logger = new NullLogger(),
    ) {}

    public function __invoke(?string $path, string $profile): string
    {
        if (null === $path || '' === $path) {
            return '';
        }

        $definition = $this->profiles->get($profile);
        if (null === $definition) {
            $this->logger->warning('StorageSrcSet: unknown image profile "{profile}".', ['profile' => $profile]);

            return '';
        }

        return $this->urls->srcset($path, $definition->variants);
    }
}
