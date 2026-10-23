<?php

declare(strict_types=1);

namespace Contenir\Asset\Mezzio\Tests\Integration\View;

use Contenir\Asset\Mezzio\Service\AnimatedSourceCheck;
use Contenir\Asset\Mezzio\Service\AssetUrlBuilder;
use Contenir\Asset\Mezzio\Service\ProfileProviderService;
use Contenir\Asset\Mezzio\Tests\TestAsset\Image\GifFactory;
use Contenir\Asset\Mezzio\Tests\TestAsset\Log\RecordingLogger;
use Contenir\Asset\Mezzio\Tests\Trait\TemporaryDirectoryTrait;
use Contenir\Asset\Mezzio\View\StorageSources;
use Contenir\Asset\Mezzio\View\StorageSrcSet;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The srcset and sources helpers offer no variants of an animated source, so
 * the template's `<img src>`, the original, is what the browser shows. The
 * sources are real files under a scratch web root.
 */
#[Group('integration')]
final class AnimatedSourceHelpersTest extends TestCase
{
    use TemporaryDirectoryTrait;

    #[Test]
    public function sourcesAreEmptyForAnAnimatedSource(): void
    {
        $this->writeFile('asset/spin.gif', GifFactory::bytes(2));

        static::assertSame('', $this->sources()('/asset/spin.gif', profile: 'tile'));
    }

    #[Test]
    public function sourcesListTheFormatsForAStillSource(): void
    {
        $this->writeFile('asset/dot.gif', GifFactory::bytes(1));

        static::assertSame(
            '<source type="image/webp" srcset="/asset/_variant/tile-320/dot.webp 320w, '
                . '/asset/_variant/tile-640/dot.webp 640w">',
            $this->sources()('/asset/dot.gif', profile: 'tile'),
        );
    }

    #[Test]
    public function srcsetCoversTheLadderForAStillSource(): void
    {
        $this->writeFile('asset/dot.gif', GifFactory::bytes(1));

        static::assertSame(
            '/asset/_variant/tile-320/dot.gif 320w, /asset/_variant/tile-640/dot.gif 640w',
            $this->srcset()('/asset/dot.gif', profile: 'tile'),
        );
    }

    #[Test]
    public function srcsetIsEmptyForAnAnimatedSource(): void
    {
        $this->writeFile('asset/spin.gif', GifFactory::bytes(2));

        static::assertSame('', $this->srcset()('/asset/spin.gif', profile: 'tile'));
    }

    /**
     * An unknown profile is still reported for an animated source, so a typo
     * in a template is not hidden by the image it happens to show.
     */
    #[Test]
    public function srcsetStillWarnsOfAnUnknownProfileForAnAnimatedSource(): void
    {
        $this->writeFile('asset/spin.gif', GifFactory::bytes(2));
        $logger = new RecordingLogger();

        $this->srcset($logger)('/asset/spin.gif', profile: 'nope');

        static::assertCount(1, $logger->records);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTemporaryDirectory();
    }

    protected function tearDown(): void
    {
        $this->tearDownTemporaryDirectory();
        parent::tearDown();
    }

    private function check(): AnimatedSourceCheck
    {
        return new AnimatedSourceCheck(new AssetUrlBuilder(''), $this->path());
    }

    private function profiles(): ProfileProviderService
    {
        return new ProfileProviderService([
            'tile' => [
                'formats'  => ['webp'],
                'variants' => [
                    'tile-320' => ['width' => 320, 'height' => 240, 'fit' => 'cover'],
                    'tile-640' => ['width' => 640, 'height' => 480, 'fit' => 'cover'],
                ],
            ],
        ]);
    }

    private function sources(): StorageSources
    {
        return new StorageSources($this->profiles(), new AssetUrlBuilder(''), new RecordingLogger(), $this->check());
    }

    private function srcset(?RecordingLogger $logger = null): StorageSrcSet
    {
        return new StorageSrcSet(
            $this->profiles(),
            new AssetUrlBuilder(''),
            $logger ?? new RecordingLogger(),
            $this->check(),
        );
    }
}
