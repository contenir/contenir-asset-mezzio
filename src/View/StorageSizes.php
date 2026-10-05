<?php

declare(strict_types=1);

namespace Contenir\Asset\Mezzio\View;

use Contenir\Asset\Mezzio\Service\ProfileProviderService;

/**
 * The configured `sizes` attribute value for a named profile, or '' when the
 * profile is unknown:
 *
 *   sizes="<?= $this->storageSizes('tile') ?>"
 *
 * A plain invokable service: call it from any template engine.
 *
 * @api
 */
final readonly class StorageSizes
{
    public function __construct(
        private ProfileProviderService $profiles,
    ) {}

    public function __invoke(string $profile): string
    {
        return $this->profiles->get($profile)->sizes ?? '';
    }
}
