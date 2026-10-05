<?php

declare(strict_types=1);

namespace Contenir\Asset\Mezzio\Tests\Integration\Service;

use Contenir\Asset\Mezzio\Service\ProfileProviderService;
use Contenir\Asset\Mezzio\Service\VariantGenerator;
use Contenir\Asset\Mezzio\Tests\Trait\TemporaryDirectoryTrait;
use Contenir\Storage\Exception\WriteException;
use Contenir\Storage\Image\ImageResizer;
use Contenir\Storage\Image\ImageResizerInterface;
use Contenir\Storage\Image\StubImageResizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function exec;
use function extension_loaded;
use function getimagesize;
use function is_file;
use function str_ends_with;
use function strlen;
use function strtolower;
use function substr;

#[Group('integration')]
final class VariantGeneratorTest extends TestCase
{
    use TemporaryDirectoryTrait;

    /**
     * @return array<string, array{string}>
     */
    public static function unsafeFilenameProvider(): array
    {
        return [
            'parent segment'     => ['..'],
            'climbing separator' => ['../../outside/pic.png'],
            'separator'          => ['sub/pic.png'],
            'null byte'          => ["pic.png\0.jpg"],
            'backslash'          => ['..\\pic.png'],
            'encoded separator'  => ['..%2fpic.png'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unsafeFolderProvider(): array
    {
        return [
            'parent segment'                => ['../outside'],
            'nested parent segment'         => ['foo/../../outside'],
            'leading slash parent segment'  => ['/../outside'],
            'null byte'                     => ["foo\0"],
            'backslash parent segment'      => ['foo\\..\\..\\outside'],
            'double-encoded parent segment' => ['%2e%2e/outside'],
        ];
    }

    #[Test]
    public function fallsBackToALowercasedSourceExtension(): void
    {
        $this->writePng('asset/foo/pic.PNG', 10, 10);

        $path = $this->generator($this->resizerFailingFor('.avif'))->generate('foo', 't-80', 'pic.avif');

        static::assertSame($this->path('asset/foo/_variant/t-80/pic.png'), $path);
    }

    #[Test]
    public function fallsBackToTheSourceFormatWhenTheRequestedOneCannotBeProduced(): void
    {
        $this->writePng('asset/foo/pic.png', 10, 10);

        $path = $this->generator($this->resizerFailingFor('.avif'))->generate('foo', 't-80', 'pic.avif');

        static::assertSame($this->path('asset/foo/_variant/t-80/pic.png'), $path);
    }

    #[Test]
    public function findsASourceWithAnUppercaseExtension(): void
    {
        if (is_file($this->writePng('asset/foo/probe.png', 1, 1)) && is_file($this->path('asset/foo/probe.PNG'))) {
            self::markTestSkipped('The filesystem is case-insensitive, so extension case cannot be observed.');
        }
        $this->writePng('asset/foo/pic.PNG', 10, 10);
        $resizer = new StubImageResizer();

        $this->generator($resizer)->generate('foo', 't-80', 'pic.webp');

        static::assertSame($this->path('asset/foo/pic.PNG'), $resizer->calls[0]['source'] ?? null);
    }

    #[Test]
    public function generatesAVariantWithImageMagick(): void
    {
        if (! extension_loaded('imagick') && '' === exec('command -v magick convert')) {
            self::markTestSkipped('Neither the imagick extension nor the ImageMagick CLI is available.');
        }
        $this->writePng('asset/foo/pic.png', 200, 150);

        $path = $this->generator(new ImageResizer())->generate('foo', 't-80', 'pic.png');

        static::assertSame($this->path('asset/foo/_variant/t-80/pic.png'), $path);
        static::assertSame([80, 60], [getimagesize($path)[0] ?? 0, getimagesize($path)[1] ?? 0]);
    }

    #[Test]
    public function generatesTheRequestedFormatFromTheSourceOfAnyExtension(): void
    {
        $this->writePng('asset/foo/pic.PNG', 10, 10);
        $resizer = new StubImageResizer();

        $path = $this->generator($resizer)->generate('/foo/', 't-80', 'pic.webp');

        static::assertSame($this->path('asset/foo/_variant/t-80/pic.webp'), $path);
        static::assertSame(strtolower($this->path('asset/foo/pic.PNG')), strtolower($resizer->calls[0]['source']));
        static::assertSame([80, 0, 70], [
            $resizer->calls[0]['width'],
            $resizer->calls[0]['height'],
            $resizer->calls[0]['quality'],
        ]);
    }

    #[Test]
    public function lowercasesTheRequestedExtension(): void
    {
        $this->writePng('asset/foo/pic.png', 10, 10);

        $path = $this->generator(new StubImageResizer())->generate('foo', 't-80', 'pic.WEBP');

        static::assertSame($this->path('asset/foo/_variant/t-80/pic.webp'), $path);
    }

    #[Test]
    public function prefersALowercaseSourceExtension(): void
    {
        $this->writePng('asset/foo/pic.png', 10, 10);
        $resizer = new StubImageResizer();

        $this->generator($resizer)->generate('foo', 't-80', 'pic.webp');

        static::assertSame($this->path('asset/foo/pic.png'), $resizer->calls[0]['source'] ?? null);
    }

    #[Test]
    public function prefersTheSourceNamedExactlyByTheRequest(): void
    {
        $this->writePng('asset/foo/pic.jpg', 10, 10);
        $this->writePng('asset/foo/pic.png', 10, 10);
        $resizer = new StubImageResizer();

        $this->generator($resizer)->generate('foo', 't-80', 'pic.png');

        static::assertSame($this->path('asset/foo/pic.png'), $resizer->calls[0]['source'] ?? null);
    }

    #[Test]
    #[DataProvider('unsafeFilenameProvider')]
    public function refusesAFilenameOutsideTheFolder(string $filename): void
    {
        $this->writePng('asset/foo/pic.png', 10, 10);
        $this->writePng('asset/foo/sub/pic.png', 10, 10);
        $this->writePng('outside/pic.png', 10, 10);
        $resizer = new StubImageResizer();

        static::assertNull($this->generator($resizer)->generate('foo', 't-80', $filename));
        static::assertSame([], $resizer->calls);
    }

    #[Test]
    #[DataProvider('unsafeFolderProvider')]
    public function refusesAFolderOutsideTheAssetDirectory(string $folder): void
    {
        /**
         * The asset directory has to exist, or `asset/../outside` would not
         * resolve and the guard would go untested.
         */
        $this->writePng('asset/foo/pic.png', 10, 10);
        $this->writePng('outside/pic.png', 10, 10);
        $resizer = new StubImageResizer();

        static::assertNull($this->generator($resizer)->generate($folder, 't-80', 'pic.png'));
        static::assertSame([], $resizer->calls);
    }

    #[Test]
    public function refusesAVariantNameThatIsAPath(): void
    {
        /**
         * Only config can declare such a name, but the name is still joined
         * onto the variant directory, so it is checked like the rest.
         */
        $this->writePng('asset/foo/pic.png', 10, 10);
        $resizer   = new StubImageResizer();
        $generator = new VariantGenerator(
            $resizer,
            new ProfileProviderService(['../../../escape' => ['width' => 10]]),
            $this->path(),
        );

        static::assertNull($generator->generate('foo', '../../../escape', 'pic.png'));
        static::assertSame([], $resizer->calls);
    }

    #[Test]
    public function returnsNullForAnUnknownVariant(): void
    {
        static::assertNull($this->generator(new StubImageResizer())->generate('foo', 'nope', 'pic.jpg'));
    }

    #[Test]
    public function returnsNullWhenNoFormatCanBeProduced(): void
    {
        $this->writePng('asset/foo/pic.png', 10, 10);

        static::assertNull($this->generator($this->resizerFailingFor(''))->generate('foo', 't-80', 'pic.avif'));
    }

    #[Test]
    public function returnsNullWhenTheSourceIsMissing(): void
    {
        static::assertNull($this->generator(new StubImageResizer())->generate('foo', 't-80', 'missing.jpg'));
    }

    #[Test]
    public function reusesAnExistingSourceFormatFallback(): void
    {
        $this->writePng('asset/foo/pic.png', 10, 10);
        $this->writeFile('asset/foo/_variant/t-80/pic.png', 'existing');

        $path = $this->generator($this->resizerFailingFor('.avif'))->generate('foo', 't-80', 'pic.avif');

        static::assertSame($this->path('asset/foo/_variant/t-80/pic.png'), $path);
    }

    #[Test]
    public function reusesAVariantThatAlreadyExists(): void
    {
        $this->writePng('asset/foo/pic.jpg', 10, 10);
        $this->writeFile('asset/foo/_variant/t-80/pic.jpg', 'existing');
        $resizer = new StubImageResizer();

        $path = $this->generator($resizer)->generate('foo', 't-80', 'pic.jpg');

        static::assertSame($this->path('asset/foo/_variant/t-80/pic.jpg'), $path);
        static::assertSame([], $resizer->calls);
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

    private function generator(ImageResizerInterface $resizer): VariantGenerator
    {
        $profiles = new ProfileProviderService([
            'thumb' => ['variants' => ['t-80' => ['width' => 80, 'height' => 0, 'fit' => 'contain', 'quality' => 70]]],
        ]);

        return new VariantGenerator($resizer, $profiles, "{$this->path()}/");
    }

    /**
     * A resizer that writes every destination except those ending in $suffix
     * ('' fails them all).
     */
    private function resizerFailingFor(string $suffix): ImageResizerInterface
    {
        $resizer = $this->createStub(ImageResizerInterface::class);
        $resizer->method('resize')
            ->willReturnCallback(function (string $source, string $dest) use ($suffix): void {
                if ('' === $suffix || str_ends_with($dest, $suffix)) {
                    throw new WriteException('cannot encode');
                }
                $this->writeFile(substr($dest, strlen($this->path()) + 1), 'variant');
            });

        return $resizer;
    }
}
