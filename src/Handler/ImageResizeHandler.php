<?php

declare(strict_types=1);

namespace Contenir\Asset\Mezzio\Handler;

use Contenir\Asset\Exception\ExceptionInterface;
use Contenir\Asset\Image\LegacyResizer;
use Override;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;

use function date;
use function mime_content_type;
use function preg_match;
use function rawurldecode;

/**
 * Serves contenir-asset's legacy dot-notation `.<dimensions>` scheme
 * (`/asset/<folder>/.480x/<file>`, as its `assetSrcSet()` emits) through
 * its `LegacyResizer`. A web server serves already generated files
 * directly; only a miss reaches this handler.
 *
 * Like {@see AssetVariantHandler}, it reads the folder, dimensions and
 * filename from the request path itself, so it works behind any router
 * and route pattern; the resizer refuses anything outside
 * `<public_path>/asset`.
 *
 * @api
 *
 * @deprecated Superseded by {@see AssetVariantHandler}, which serves keyed
 *             variants (incl. WebP/AVIF) at `/asset/<folder>/_variant/<name>/<file>`.
 *             Kept working for sites still on the `.<dimensions>` scheme.
 */
final class ImageResizeHandler implements RequestHandlerInterface
{
    private const string PATH_PATTERN = '#^/asset/(?<folder>.+?)/\.(?<dimensions>[\d.]+x[\d.]*)/(?<filename>[^/]+)$#';

    public function __construct(
        private readonly LegacyResizer $resizer,
        private readonly ResponseFactoryInterface $responses,
        private readonly StreamFactoryInterface $streams,
    ) {}

    /**
     * @throws RuntimeException If the resized file cannot be opened.
     */
    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if (! $this->resizer->isConfigured()) {
            return $this->answer(501, 'Image resizing is not configured');
        }

        $match = [];
        if (1 !== preg_match(self::PATH_PATTERN, $request->getUri()->getPath(), $match)) {
            return $this->answer(404, 'File not found');
        }

        /** @var array{folder: string, dimensions: string, filename: string} $match Every named group takes part in a match. */

        try {
            $path = $this->resizer->resize(
                rawurldecode($match['folder']),
                rawurldecode($match['dimensions']),
                rawurldecode($match['filename']),
            );
        } catch (ExceptionInterface $e) {
            return $this->answer(500, $e->getMessage());
        }

        return null === $path ? $this->answer(404, 'File not found') : $this->file($path);
    }

    /**
     * @throws RuntimeException If the response body cannot be written.
     */
    private function answer(int $status, string $reason): ResponseInterface
    {
        $response = $this->responses->createResponse($status)->withHeader('Content-Type', 'text/plain');
        $response->getBody()->write($reason);

        return $response;
    }

    /**
     * @throws RuntimeException If the resized file cannot be opened.
     */
    private function file(string $path): ResponseInterface
    {
        $mimeType = mime_content_type($path);

        return $this->responses
            ->createResponse(200)
            ->withHeader('Content-Type', false === $mimeType ? 'application/octet-stream' : $mimeType)
            ->withHeader('X-Contenir-Generated', date('r'))
            ->withBody($this->streams->createStreamFromFile($path));
    }
}
