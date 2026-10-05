<?php

declare(strict_types=1);

namespace Contenir\Asset\Mezzio\Tests\Unit\View;

use Contenir\Asset\Mezzio\Service\AssetUrlBuilder;
use Contenir\Asset\Mezzio\Service\ProfileProviderService;
use Contenir\Asset\Mezzio\Tests\TestAsset\Log\RecordingLogger;
use Contenir\Asset\Mezzio\View\StorageUrl;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

#[Group('unit')]
final class StorageUrlTest extends TestCase
{
    #[Test]
    public function anEmptyVariantMeansTheOriginal(): void
    {
        static::assertSame('/a/photo.jpg', $this->helper()('/a/photo.jpg', variant: ''));
    }

    #[Test]
    public function knownVariantLogsNothing(): void
    {
        $logger = new RecordingLogger();

        $this->helper($logger)('/a/photo.jpg', variant: 'tile-640');

        static::assertSame([], $logger->records);
    }

    #[Test]
    public function returnsEmptyStringForAnEmptyPath(): void
    {
        static::assertSame('', $this->helper()('', variant: 'tile-640'));
    }

    #[Test]
    public function returnsEmptyStringForNullPath(): void
    {
        static::assertSame('', $this->helper()(null));
    }

    #[Test]
    public function returnsOriginalWhenNoVariantGiven(): void
    {
        static::assertSame('/a/photo.jpg', $this->helper()('/a/photo.jpg'));
    }

    #[Test]
    public function returnsVariantUrl(): void
    {
        static::assertSame('/a/_variant/tile-640/photo.jpg', $this->helper()('/a/photo.jpg', variant: 'tile-640'));
    }

    #[Test]
    public function returnsVariantUrlInRequestedFormat(): void
    {
        static::assertSame('/a/_variant/tile-640/photo.webp', $this->helper()(
            '/a/photo.jpg',
            variant: 'tile-640',
            format: 'webp',
        ));
    }

    #[Test]
    public function unknownVariantIsSilentWithoutALogger(): void
    {
        $helper = new StorageUrl(new ProfileProviderService([]), new AssetUrlBuilder(''));

        static::assertSame('/a/_variant/nope/photo.jpg', $helper('/a/photo.jpg', variant: 'nope'));
    }

    #[Test]
    public function warnsOnUnknownVariantButStillEmitsUrl(): void
    {
        $logger = new RecordingLogger();

        $url = $this->helper($logger)('/a/photo.jpg', variant: 'nope-999');

        static::assertSame('/a/_variant/nope-999/photo.jpg', $url, 'URL construction stays deterministic.');
        static::assertSame(
            [['level' => LogLevel::WARNING, 'message' => 'StorageUrl: unknown variant "nope-999".']],
            $logger->records,
        );
    }

    private function helper(?RecordingLogger $logger = null): StorageUrl
    {
        return new StorageUrl(
            new ProfileProviderService([
                'tile' => ['variants' => ['tile-640' => ['width' => 640, 'height' => 480, 'fit' => 'cover']]],
            ]),
            new AssetUrlBuilder(''),
            $logger ?? new RecordingLogger(),
        );
    }
}
