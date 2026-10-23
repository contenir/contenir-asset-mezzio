<?php

declare(strict_types=1);

namespace Contenir\Asset\Mezzio\Tests\Integration\Handler;

use Contenir\Asset\Image\LegacyResizer;
use Contenir\Asset\Mezzio\Handler\Factory\ImageResizeHandlerFactory;
use Contenir\Asset\Mezzio\Handler\ImageResizeHandler;
use Contenir\Asset\Mezzio\Tests\TestAsset\Container\InMemoryContainer;
use Contenir\Asset\Mezzio\Tests\TestAsset\Image\CopyingImageConverter;
use Contenir\Asset\Mezzio\Tests\Trait\TemporaryDirectoryTrait;
use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\StreamFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

use function is_file;
use function str_contains;

/**
 * Serves real files resized into a scratch web root through contenir-asset's
 * legacy resizer.
 *
 * @mago-expect analysis:deprecated-class Tests the deprecated legacy handler.
 */
#[Group('integration')]
#[Group('handler')]
final class ImageResizeHandlerTest extends TestCase
{
    use TemporaryDirectoryTrait;

    private CopyingImageConverter $converter;

    /**
     * @return array<string, array{string}>
     */
    public static function rejectedPathProvider(): array
    {
        return [
            'missing file'         => ['/asset/gallery/.20x/none.png'],
            'path traversal'       => ['/asset/../.20x/secret.txt'],
            'encoded traversal'    => ['/asset/%2E%2E/.20x/secret.txt'],
            'malformed dimensions' => ['/asset/gallery/.20;rm/photo.png'],
            'a keyed variant path' => ['/asset/gallery/_variant/card/photo.png'],
            'an original'          => ['/asset/gallery/photo.png'],
            'outside /asset/'      => ['/other/asset/gallery/.20x/photo.png'],
            'a trailing segment'   => ['/asset/gallery/.20x/photo.png/extra'],
        ];
    }

    #[DataProvider('rejectedPathProvider')]
    #[Test]
    public function answersNotFoundForUnsafeOrMissingFiles(string $path): void
    {
        static::assertSame([404, []], [$this->handle($path)->getStatusCode(), $this->converter->calls]);
    }

    #[Test]
    public function answersNotImplementedWithoutAConverter(): void
    {
        static::assertSame(501, $this->handle('/asset/gallery/.20x/photo.png', converter: null)->getStatusCode());
    }

    #[Test]
    public function decodesTheFolderAndFilenameOnce(): void
    {
        $this->writePng('asset/my folder/pic one.png', 40, 20);

        $response = $this->handle('/asset/my%20folder/.20x/pic%20one.png');

        static::assertSame([200, true], [
            $response->getStatusCode(),
            is_file($this->path('asset/my folder/.20x/pic one.png')),
        ]);
    }

    #[Test]
    public function factoryTakesItsDependenciesFromTheContainer(): void
    {
        $container = new InMemoryContainer([
            LegacyResizer::class            => new LegacyResizer($this->path(), $this->converter),
            ResponseFactoryInterface::class => new ResponseFactory(),
            StreamFactoryInterface::class   => new StreamFactory(),
        ]);

        static::assertInstanceOf(ImageResizeHandler::class, (new ImageResizeHandlerFactory())($container));
    }

    #[Test]
    public function generatesAndServesTheResizedImage(): void
    {
        $response = $this->handle('/asset/gallery/.20x/photo.png');

        static::assertSame(
            [200, 'image/png', true, '20x'],
            [
                $response->getStatusCode(),
                $response->getHeaderLine('Content-Type'),
                is_file($this->path('asset/gallery/.20x/photo.png')),
                $this->converter->calls[0]['operations'][4] ?? null,
            ],
        );
    }

    #[Test]
    public function reportsADestinationThatCannotBeCreated(): void
    {
        $this->writeFile('asset/gallery/.20x', 'a file where the directory should be');

        $response = $this->handle('/asset/gallery/.20x/photo.png');

        static::assertSame([500, true], [
            $response->getStatusCode(),
            str_contains((string) $response->getBody(), 'Cannot create'),
        ]);
    }

    #[Test]
    public function servesAnExistingResizeWithoutConverting(): void
    {
        $this->writeFile('asset/gallery/.20x/photo.png', 'cached');

        $response = $this->handle('/asset/gallery/.20x/photo.png');

        static::assertSame(['cached', []], [(string) $response->getBody(), $this->converter->calls]);
    }

    protected function setUp(): void
    {
        $this->setUpTemporaryDirectory();
        $this->converter = new CopyingImageConverter();
        $this->writePng('asset/gallery/photo.png', 40, 20);
        $this->writeFile('secret.txt', 'top secret');
    }

    protected function tearDown(): void
    {
        $this->tearDownTemporaryDirectory();
    }

    private function handle(
        string $path,
        ?CopyingImageConverter $converter = new CopyingImageConverter(),
    ): ResponseInterface {
        $this->converter = $converter ?? $this->converter;
        $handler         = new ImageResizeHandler(
            new LegacyResizer($this->path(), $converter),
            new ResponseFactory(),
            new StreamFactory(),
        );

        return $handler->handle(new ServerRequest(uri: "https://example.test{$path}"));
    }
}
