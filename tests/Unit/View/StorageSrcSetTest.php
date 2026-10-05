<?php

declare(strict_types=1);

namespace Contenir\Asset\Mezzio\Tests\Unit\View;

use Contenir\Asset\Mezzio\Service\AssetUrlBuilder;
use Contenir\Asset\Mezzio\Service\ProfileProviderService;
use Contenir\Asset\Mezzio\Tests\TestAsset\Log\RecordingLogger;
use Contenir\Asset\Mezzio\View\StorageSrcSet;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

#[Group('unit')]
final class StorageSrcSetTest extends TestCase
{
    #[Test]
    public function rendersSrcsetOverProfileVariants(): void
    {
        $expected = '/a/_variant/tile-320/photo.jpg 320w, /a/_variant/tile-640/photo.jpg 640w';

        static::assertSame($expected, $this->helper()('/a/photo.jpg', profile: 'tile'));
    }

    #[Test]
    public function returnsEmptyStringForAnEmptyPath(): void
    {
        $logger = new RecordingLogger();

        static::assertSame('', $this->helper($logger)('', profile: 'nope'));
        static::assertSame([], $logger->records);
    }

    #[Test]
    public function returnsEmptyStringForNullPath(): void
    {
        static::assertSame('', $this->helper()(null, profile: 'tile'));
    }

    #[Test]
    public function unknownProfileIsSilentWithoutALogger(): void
    {
        $helper = new StorageSrcSet(new ProfileProviderService([]), new AssetUrlBuilder(''));

        static::assertSame('', $helper('/a/photo.jpg', profile: 'nope'));
    }

    #[Test]
    public function warnsAndReturnsEmptyOnUnknownProfile(): void
    {
        $logger = new RecordingLogger();

        $result = $this->helper($logger)('/a/photo.jpg', profile: 'nope');

        static::assertSame('', $result);
        static::assertSame(
            [['level' => LogLevel::WARNING, 'message' => 'StorageSrcSet: unknown image profile "nope".']],
            $logger->records,
        );
    }

    private function helper(?RecordingLogger $logger = null): StorageSrcSet
    {
        $profiles = new ProfileProviderService([
            'tile' => [
                'variants' => [
                    'tile-320' => ['width' => 320, 'height' => 240, 'fit' => 'cover'],
                    'tile-640' => ['width' => 640, 'height' => 480, 'fit' => 'cover'],
                ],
            ],
        ]);

        return new StorageSrcSet($profiles, new AssetUrlBuilder(''), $logger ?? new RecordingLogger());
    }
}
