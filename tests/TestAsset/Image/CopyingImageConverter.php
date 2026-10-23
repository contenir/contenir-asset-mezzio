<?php

declare(strict_types=1);

namespace Contenir\Asset\Mezzio\Tests\TestAsset\Image;

use Contenir\Asset\Image\ImageConverterInterface;
use Override;

use function copy;

/**
 * Copies the source to the destination and records each call, standing in
 * for ImageMagick behind contenir-asset's legacy resizer.
 */
final class CopyingImageConverter implements ImageConverterInterface
{
    /**
     * @var list<array{source: string, destination: string, operations: list<string>}>
     */
    public array $calls = [];

    #[Override]
    public function convert(string $source, string $destination, array $operations): void
    {
        $this->calls[] = ['source' => $source, 'destination' => $destination, 'operations' => $operations];
        copy($source, $destination);
    }
}
