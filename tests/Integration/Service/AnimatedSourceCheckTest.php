<?php

declare(strict_types=1);

namespace Contenir\Asset\Mezzio\Tests\Integration\Service;

use Contenir\Asset\Mezzio\Service\AnimatedSourceCheck;
use Contenir\Asset\Mezzio\Service\AssetUrlBuilder;
use Contenir\Asset\Mezzio\Tests\TestAsset\Image\GifFactory;
use Contenir\Asset\Mezzio\Tests\Trait\TemporaryDirectoryTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function unlink;

/**
 * The check reads each file's header from under a scratch web root.
 */
#[Group('integration')]
final class AnimatedSourceCheckTest extends TestCase
{
    use TemporaryDirectoryTrait;

    /**
     * @return array<string, array{string, string}>
     */
    public static function storedPathProvider(): array
    {
        return [
            'web path under the public base'  => ['/asset', '/asset/library/spin.gif'],
            'key relative to the public base' => ['/asset', 'library/spin.gif'],
            'no public base'                  => ['', '/asset/library/spin.gif'],
        ];
    }

    #[Test]
    public function doesNotInspectAnObjectStore(): void
    {
        $this->writeFile('asset/spin.gif', GifFactory::bytes(2));

        $check = new AnimatedSourceCheck(new AssetUrlBuilder(''), $this->path(), local: false);

        static::assertFalse($check->isAnimated('/asset/spin.gif'));
    }

    /**
     * A JPEG cannot animate, so it is not read: these bytes would say
     * otherwise if it were.
     */
    #[Test]
    public function doesNotReadAFormatThatCannotAnimate(): void
    {
        $this->writeFile('asset/photo.jpg', GifFactory::bytes(2));

        static::assertFalse($this->check()->isAnimated('/asset/photo.jpg'));
    }

    #[Test]
    public function doesNotReadOutsideTheWebRoot(): void
    {
        $this->writeFile('outside.gif', GifFactory::bytes(2));

        static::assertFalse((new AnimatedSourceCheck(new AssetUrlBuilder(''), $this->path('public')))->isAnimated(
            '/../outside.gif',
        ));
    }

    #[Test]
    #[DataProvider('storedPathProvider')]
    public function findsTheFileWhereTheWebServerServesIt(string $publicBase, string $path): void
    {
        $this->writeFile('asset/library/spin.gif', GifFactory::bytes(2));

        static::assertTrue($this->check($publicBase)->isAnimated($path));
    }

    #[Test]
    public function keepsItsAnswerForTheRestOfTheRequest(): void
    {
        $file  = $this->writeFile('asset/spin.gif', GifFactory::bytes(2));
        $check = $this->check();
        $check->isAnimated('/asset/spin.gif');
        unlink($file);

        static::assertTrue($check->isAnimated('/asset/spin.gif'));
    }

    #[Test]
    public function readsAFileWhoseNameNeedsEncoding(): void
    {
        $this->writeFile('asset/my spin (1).gif', GifFactory::bytes(2));

        static::assertTrue($this->check()->isAnimated('/asset/my spin (1).gif'));
    }

    #[Test]
    public function readsAnUppercaseExtension(): void
    {
        $this->writeFile('asset/SPIN.GIF', GifFactory::bytes(2));

        static::assertTrue($this->check()->isAnimated('/asset/SPIN.GIF'));
    }

    #[Test]
    public function reportsAMissingFileAsNotAnimated(): void
    {
        static::assertFalse($this->check()->isAnimated('/asset/missing.gif'));
    }

    #[Test]
    public function reportsAnEmptyPathAsNotAnimated(): void
    {
        static::assertFalse($this->check()->isAnimated(''));
    }

    #[Test]
    public function reportsAStillImageAsNotAnimated(): void
    {
        $this->writeFile('asset/dot.gif', GifFactory::bytes(1));

        static::assertFalse($this->check()->isAnimated('/asset/dot.gif'));
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

    private function check(string $publicBase = ''): AnimatedSourceCheck
    {
        return new AnimatedSourceCheck(new AssetUrlBuilder($publicBase), $this->path());
    }
}
