<?php

declare(strict_types=1);

namespace Contenir\Asset\Mezzio\Tests\Integration;

use Contenir\Asset\Mezzio\Handler\AssetVariantGenerateHandler;
use Contenir\Asset\Mezzio\Handler\AssetVariantHandler;
use Contenir\Asset\Mezzio\Tests\TestAsset\Storage\FakeOnDemandStorage;
use Contenir\Asset\Mezzio\Tests\Trait\MezzioContainerTrait;
use Contenir\Asset\Mezzio\Tests\Trait\TemporaryDirectoryTrait;
use Contenir\Storage\Image\ImageResizerInterface;
use Contenir\Storage\Image\StubImageResizer;
use Contenir\Storage\StorageManager;
use Laminas\Diactoros\ServerRequest;
use Mezzio\Application;
use Mezzio\Handler\NotFoundHandler;
use Mezzio\Router\Middleware\DispatchMiddleware;
use Mezzio\Router\Middleware\ImplicitHeadMiddleware;
use Mezzio\Router\Middleware\MethodNotAllowedMiddleware;
use Mezzio\Router\Middleware\RouteMiddleware;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

use function glob;
use function is_file;
use function parse_str;

use const GLOB_BRACE;

/**
 * Drives both handlers through a real Mezzio application: container, FastRoute
 * routing and the middleware pipeline, routed the way the README documents.
 *
 * @mago-expect lint:no-literal-password A dummy shared secret.
 */
#[Group('integration')]
#[Group('handler')]
final class MezzioPipelineTest extends TestCase
{
    use MezzioContainerTrait;
    use TemporaryDirectoryTrait;

    private const string SECRET = 's3cret';

    private StubImageResizer $resizer;

    private FakeOnDemandStorage $bucket;

    /**
     * @return array<string, array{string}>
     */
    public static function traversalProvider(): array
    {
        return [
            'encoded parent folder'        => ['/asset/%2e%2e/_variant/card/pic.png'],
            'encoded nested parent folder' => ['/asset/news/%2e%2e/%2e%2e/outside/_variant/card/pic.png'],
            'double-encoded parent folder' => ['/asset/%252e%252e/_variant/card/pic.png'],
            'encoded null byte'            => ['/asset/news%00/_variant/card/pic.png'],
            'encoded slash in filename'    => ['/asset/news/_variant/card/..%2F..%2Fpic.png'],
        ];
    }

    #[Test]
    #[DataProvider('traversalProvider')]
    public function aPathClimbingOutOfTheAssetDirectoryIsNotFound(string $path): void
    {
        $response = $this->dispatch($path);

        static::assertSame(404, $response->getStatusCode());
        static::assertSame([], $this->resizer->calls);
        static::assertSame([], glob($this->path('{,*/,*/*/}_variant'), GLOB_BRACE));
    }

    #[Test]
    public function generateEndpointGeneratesThroughTheRegisteredBackend(): void
    {
        $response = $this->dispatch(
            '/asset-variant/generate?key=news/pic__card.avif',
            'POST',
            [AssetVariantGenerateHandler::SECRET_HEADER => self::SECRET],
        );

        static::assertSame(200, $response->getStatusCode());
        static::assertSame('{"url":"https:\/\/cdn.test\/news\/pic__card.avif"}', (string) $response->getBody());
        static::assertSame(['news/pic__card.avif'], $this->bucket->generated);
    }

    #[Test]
    public function generateEndpointRefusesAWrongSecret(): void
    {
        $response = $this->dispatch(
            '/asset-variant/generate?key=news/pic__card.avif',
            'POST',
            [AssetVariantGenerateHandler::SECRET_HEADER => 'wrong'],
        );

        static::assertSame(403, $response->getStatusCode());
        static::assertSame([], $this->bucket->generated);
    }

    #[Test]
    public function servesAMissingVariantGeneratedOnDemand(): void
    {
        $response = $this->dispatch('/asset/news/2024/_variant/card/my%20pic.webp');

        static::assertSame(200, $response->getStatusCode());
        static::assertSame('image/webp', $response->getHeaderLine('Content-Type'));
        static::assertSame('public, max-age=31536000', $response->getHeaderLine('Cache-Control'));
        static::assertSame('STUB:320x0:Cover', (string) $response->getBody());
        static::assertTrue(is_file($this->path('public/asset/news/2024/_variant/card/my pic.webp')));
    }

    #[Test]
    public function unknownVariantIsNotFound(): void
    {
        static::assertSame(404, $this->dispatch('/asset/news/2024/_variant/nope/my%20pic.png')->getStatusCode());
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTemporaryDirectory();
        $this->writePng('public/asset/news/2024/my pic.png', 10, 10);
        $this->writePng('public/asset/pic.png', 10, 10);
        $this->writePng('public/pic.png', 10, 10);
        $this->resizer = new StubImageResizer();
        $this->bucket  = new FakeOnDemandStorage([]);
    }

    protected function tearDown(): void
    {
        $this->tearDownTemporaryDirectory();
        parent::tearDown();
    }

    /**
     * @param array<string, string> $headers
     */
    private function dispatch(string $uri, string $method = 'GET', array $headers = []): ResponseInterface
    {
        $manager = new StorageManager();
        $manager->register('r2', $this->bucket);

        $container = $this->mezzioContainer([
            'storage'      => [
                'backend'  => ['local' => [
                    'type'            => 'local',
                    'root_path'       => $this->path('public'),
                    'generate_secret' => self::SECRET,
                ]],
                'variants' => ['card' => ['width' => 320]],
            ],
            'dependencies' => [
                'aliases'  => [ImageResizerInterface::class => StubImageResizer::class],
                'services' => [StubImageResizer::class => $this->resizer, StorageManager::class => $manager],
            ],
        ]);

        /** @var Application $app */
        $app = $container->get(Application::class);
        $app->pipe(RouteMiddleware::class);
        $app->pipe(ImplicitHeadMiddleware::class);
        $app->pipe(MethodNotAllowedMiddleware::class);
        $app->pipe(DispatchMiddleware::class);
        $app->pipe(NotFoundHandler::class);
        $app->get('/asset/{path:.+}', AssetVariantHandler::class, 'asset.variant');
        $app->route('/asset-variant/generate', AssetVariantGenerateHandler::class, ['GET', 'POST'], 'asset.generate');

        $request = new ServerRequest(
            uri: "https://example.test{$uri}",
            method: $method,
            headers: $headers,
        );
        parse_str($request->getUri()->getQuery(), $query);

        return $app->handle($request->withQueryParams($query));
    }
}
