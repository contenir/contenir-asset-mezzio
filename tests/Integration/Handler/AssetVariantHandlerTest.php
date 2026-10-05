<?php

declare(strict_types=1);

namespace Contenir\Asset\Mezzio\Tests\Integration\Handler;

use Contenir\Asset\Mezzio\Handler\AssetVariantHandler;
use Contenir\Asset\Mezzio\Service\ProfileProviderService;
use Contenir\Asset\Mezzio\Service\VariantGenerator;
use Contenir\Asset\Mezzio\Tests\Trait\TemporaryDirectoryTrait;
use Contenir\Storage\Image\ImageResizerInterface;
use Contenir\Storage\Image\StubImageResizer;
use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\StreamFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;

use function file_exists;
use function glob;

use const GLOB_BRACE;

/**
 * Serves real files generated into a scratch web root.
 */
#[Group('integration')]
#[Group('handler')]
final class AssetVariantHandlerTest extends TestCase
{
    use TemporaryDirectoryTrait;

    /**
     * @return array<string, array{string, string}>
     */
    public static function mediaTypeProvider(): array
    {
        return [
            'avif'      => ['avif', 'image/avif'],
            'gif'       => ['gif', 'image/gif'],
            'jpeg'      => ['jpeg', 'image/jpeg'],
            'jpg'       => ['jpg', 'image/jpeg'],
            'png'       => ['png', 'image/png'],
            'webp'      => ['webp', 'image/webp'],
            'upper jpg' => ['JPG', 'image/jpeg'],
            'unknown'   => ['bmp', 'application/octet-stream'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function nonVariantPathProvider(): array
    {
        return [
            'an original'             => ['/asset/foo/pic.png'],
            'outside /asset/'         => ['/other/asset/foo/_variant/thumb/pic.png'],
            'a trailing segment'      => ['/asset/foo/_variant/thumb/pic.png/extra'],
            'a name outside [A-z0-9]' => ['/asset/foo/_variant/th%20umb/pic.png'],
            'no folder'               => ['/asset//_variant/thumb/pic.png'],
            'no filename'             => ['/asset/foo/_variant/thumb/'],
        ];
    }

    /**
     * Each path would reach `<root>/pic.png` or `<root>/outside/pic.png`,
     * both of which exist, if the decoded value were joined on unchecked.
     *
     * @return array<string, array{string}>
     */
    public static function traversalPathProvider(): array
    {
        return [
            'encoded parent folder'        => ['/asset/%2e%2e/_variant/thumb/pic.png'],
            'upper-case encoded parent'    => ['/asset/%2E%2E/_variant/thumb/pic.png'],
            'mixed encoded parent'         => ['/asset/.%2e/_variant/thumb/pic.png'],
            'nested encoded parent'        => ['/asset/foo/%2e%2e/%2e%2e/outside/_variant/thumb/pic.png'],
            'literal parent folder'        => ['/asset/../_variant/thumb/pic.png'],
            'double-encoded parent folder' => ['/asset/%252e%252e/_variant/thumb/pic.png'],
            'encoded null byte in folder'  => ['/asset/foo%00/_variant/thumb/pic.png'],
            'encoded backslash in folder'  => ['/asset/..%5c/_variant/thumb/pic.png'],
            'encoded slash in filename'    => ['/asset/foo/_variant/thumb/..%2f..%2foutside%2fpic.png'],
            'encoded parent filename'      => ['/asset/foo/_variant/thumb/%2e%2e'],
            'encoded null byte filename'   => ['/asset/foo/_variant/thumb/pic.png%00.jpg'],
        ];
    }

    #[Test]
    #[DataProvider('traversalPathProvider')]
    public function answersNotFoundForAPathThatClimbsOutOfTheAssetDirectory(string $path): void
    {
        $this->writePng('asset/foo/pic.png', 10, 10);
        $this->writePng('pic.png', 10, 10);
        $this->writePng('outside/pic.png', 10, 10);
        $resizer = new StubImageResizer();

        $response = $this->handle($path, $resizer);

        static::assertSame(404, $response->getStatusCode());
        static::assertSame([], $resizer->calls, 'Nothing may be resized.');
        static::assertSame([], glob($this->path('{,*/,*/*/}_variant'), GLOB_BRACE), 'Nothing may be written.');
    }

    #[Test]
    #[DataProvider('nonVariantPathProvider')]
    public function answersNotFoundForAPathThatIsNotAVariant(string $path): void
    {
        $this->writePng('asset/foo/pic.png', 10, 10);
        $resizer = new StubImageResizer();

        static::assertSame(404, $this->handle($path, $resizer)->getStatusCode());
        static::assertSame([], $resizer->calls);
    }

    #[Test]
    public function answersNotFoundWhenTheGeneratorReportsAFileThatIsNotThere(): void
    {
        $this->writePng('asset/foo/pic.png', 10, 10);

        static::assertSame(
            404,
            $this->handle('/asset/foo/_variant/thumb/pic.png', $this->createStub(ImageResizerInterface::class))
                ->getStatusCode(),
        );
    }

    #[Test]
    public function answersNotFoundWhenTheVariantCannotBeProduced(): void
    {
        static::assertSame(404, $this->handle('/asset/foo/_variant/thumb/none.png')->getStatusCode());
    }

    #[Test]
    public function decodesThePathExactlyOnce(): void
    {
        $this->writePng('asset/foo/a+b%20c.png', 10, 10);

        $response = $this->handle('/asset/foo/_variant/thumb/a+b%2520c.png');

        static::assertSame(200, $response->getStatusCode());
        static::assertTrue(file_exists($this->path('asset/foo/_variant/thumb/a+b%20c.png')));
    }

    #[Test]
    public function leavesOutTheContentLengthWhenTheStreamSizeIsUnknown(): void
    {
        $this->writePng('asset/foo/pic.png', 10, 10);
        $stream = $this->createStub(StreamInterface::class);
        $stream->method('getSize')->willReturn(null);
        $streams = $this->createStub(StreamFactoryInterface::class);
        $streams->method('createStreamFromFile')->willReturn($stream);

        $response = $this->handle('/asset/foo/_variant/thumb/pic.png', streams: $streams);

        static::assertSame(200, $response->getStatusCode());
        static::assertFalse($response->hasHeader('Content-Length'));
    }

    #[Test]
    #[DataProvider('mediaTypeProvider')]
    public function namesTheMediaTypeOfTheServedFormat(string $extension, string $mediaType): void
    {
        $this->writePng('asset/foo/pic.png', 10, 10);

        $response = $this->handle("/asset/foo/_variant/thumb/pic.{$extension}");

        static::assertSame($mediaType, $response->getHeaderLine('Content-Type'));
    }

    #[Test]
    public function servesTheGeneratedVariantWithLongLivedCaching(): void
    {
        $this->writePng('asset/My Folder/pic one.png', 10, 10);

        $response = $this->handle('/asset/My%20Folder/_variant/thumb/pic%20one.png');

        static::assertSame(200, $response->getStatusCode());
        static::assertSame('STUB:20x20:Cover', (string) $response->getBody());
        static::assertSame(
            [
                'Content-Type'   => ['image/png'],
                'Cache-Control'  => ['public, max-age=31536000'],
                'Content-Length' => ['16'],
            ],
            $response->getHeaders(),
        );
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

    private function handle(
        string $path,
        ?ImageResizerInterface $resizer = null,
        ?StreamFactoryInterface $streams = null,
    ): ResponseInterface {
        $profiles = new ProfileProviderService(['thumb' => ['width' => 20, 'height' => 20]]);
        $handler  = new AssetVariantHandler(
            new VariantGenerator($resizer ?? new StubImageResizer(), $profiles, $this->path()),
            new ResponseFactory(),
            $streams ?? new StreamFactory(),
        );

        return $handler->handle(new ServerRequest(
            uri: "https://example.test{$path}",
            method: 'GET',
        ));
    }
}
