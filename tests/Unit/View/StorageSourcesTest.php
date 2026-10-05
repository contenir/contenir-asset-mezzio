<?php

declare(strict_types=1);

namespace Contenir\Asset\Mezzio\Tests\Unit\View;

use Contenir\Asset\Mezzio\Service\AssetUrlBuilder;
use Contenir\Asset\Mezzio\Service\ProfileProviderService;
use Contenir\Asset\Mezzio\Tests\TestAsset\Log\RecordingLogger;
use Contenir\Asset\Mezzio\View\StorageSources;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

#[Group('unit')]
final class StorageSourcesTest extends TestCase
{
    #[Test]
    public function lazyModeEmitsDataLazysrcSrcset(): void
    {
        static::assertSame(
            '<source type="image/avif" data-lazysrc-srcset="/a/_variant/tile-320/photo.avif 320w" sizes="100vw">'
                . '<source type="image/webp" data-lazysrc-srcset="/a/_variant/tile-320/photo.webp 320w" sizes="100vw">',
            $this->helper()('/a/photo.jpg', profile: 'tile', lazy: true),
        );
    }

    #[Test]
    public function omitsTheSizesAttributeWhenTheProfileHasNone(): void
    {
        $helper = new StorageSources(
            new ProfileProviderService(['tile' => ['variants' => ['tile-1' => ['width' => 1]], 'formats' => ['webp']]]),
            new AssetUrlBuilder(''),
        );

        static::assertSame('<source type="image/webp" srcset="/_variant/tile-1/a.webp 1w">', $helper(
            'a.jpg',
            profile: 'tile',
        ));
    }

    #[Test]
    public function rendersOneSourceTagPerFormatWithSizes(): void
    {
        static::assertSame(
            '<source type="image/avif" srcset="/a/_variant/tile-320/photo.avif 320w" sizes="100vw">'
                . '<source type="image/webp" srcset="/a/_variant/tile-320/photo.webp 320w" sizes="100vw">',
            $this->helper()('/a/photo.jpg', profile: 'tile'),
        );
    }

    #[Test]
    public function returnsEmptyStringForAnEmptyPath(): void
    {
        $logger = new RecordingLogger();

        static::assertSame('', $this->helper(logger: $logger)('', profile: 'nope'));
        static::assertSame([], $logger->records);
    }

    #[Test]
    public function returnsEmptyStringForNullPath(): void
    {
        static::assertSame('', $this->helper()(null, profile: 'tile'));
    }

    #[Test]
    public function returnsEmptyStringWhenProfileDeclaresNoFormats(): void
    {
        static::assertSame('', $this->helper([])('/a/photo.jpg', profile: 'tile'));
    }

    #[Test]
    public function returnsEmptyStringWhenTheProfileHasNoVariants(): void
    {
        $helper = new StorageSources(new ProfileProviderService([
            'empty' => ['variants' => [], 'formats' => ['webp']],
        ]), new AssetUrlBuilder(''));

        static::assertSame('', $helper('a.jpg', profile: 'empty'));
    }

    #[Test]
    public function unknownProfileIsSilentWithoutALogger(): void
    {
        $helper = new StorageSources(new ProfileProviderService([]), new AssetUrlBuilder(''));

        static::assertSame('', $helper('/a/photo.jpg', profile: 'nope'));
    }

    #[Test]
    public function warnsAndReturnsEmptyOnUnknownProfile(): void
    {
        $logger = new RecordingLogger();

        $result = $this->helper(logger: $logger)('/a/photo.jpg', profile: 'nope');

        static::assertSame('', $result);
        static::assertSame(
            [['level' => LogLevel::WARNING, 'message' => 'StorageSources: unknown image profile "nope".']],
            $logger->records,
        );
    }

    /**
     * @param list<string> $formats
     */
    private function helper(array $formats = ['avif', 'webp'], ?RecordingLogger $logger = null): StorageSources
    {
        $profiles = new ProfileProviderService([
            'tile' => [
                'sizes'    => '100vw',
                'formats'  => $formats,
                'variants' => [
                    'tile-320' => ['width' => 320, 'height' => 240, 'fit' => 'cover'],
                ],
            ],
        ]);

        return new StorageSources($profiles, new AssetUrlBuilder(''), $logger ?? new RecordingLogger());
    }
}
