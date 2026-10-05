<?php

declare(strict_types=1);

namespace Contenir\Asset\Mezzio\Handler;

use Contenir\Asset\Mezzio\Service\VariantGenerator;
use Override;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;

use function is_file;
use function pathinfo;
use function preg_match;
use function rawurldecode;
use function sprintf;

use const PATHINFO_EXTENSION;

/**
 * Serves keyed image variants on demand under
 * `/asset/<folder>/_variant/<name>/<filename>`.
 *
 * Existing variant files are served directly by the web server; only missing
 * ones reach this handler, which materialises them via {@see VariantGenerator}
 * and answers with the file as the response body, or an empty 404 when the
 * variant cannot be produced.
 *
 * The handler reads the folder, name and filename from the request path
 * itself rather than from route attributes, so it works behind any router and
 * route pattern, and the values are percent-decoded exactly once whatever the
 * router did. The decoded values are checked by
 * {@see \Contenir\Asset\Mezzio\Security\PathGuard} inside the generator before
 * any filesystem access.
 *
 * @api
 */
final class AssetVariantHandler implements RequestHandlerInterface
{
    public const string CACHE_CONTROL = 'public, max-age=31536000';

    private const string PATH_PATTERN = '#^/asset/(?<folder>.+?)/_variant/(?<name>[A-Za-z0-9_-]+)/(?<filename>[^/]+)$#';

    /** @var array<string, string> Output extension => media type. */
    private const array MEDIA_TYPES = [
        'avif' => 'image/avif',
        'gif'  => 'image/gif',
        'jpeg' => 'image/jpeg',
        'jpg'  => 'image/jpeg',
        'png'  => 'image/png',
        'webp' => 'image/webp',
    ];

    private const string FALLBACK_MEDIA_TYPE = 'application/octet-stream';

    public function __construct(
        private readonly VariantGenerator $generator,
        private readonly ResponseFactoryInterface $responses,
        private readonly StreamFactoryInterface $streams,
    ) {}

    /**
     * The generator always writes a lower-case extension, so the map needs no
     * case folding.
     */
    private static function mediaType(string $path): string
    {
        return self::MEDIA_TYPES[pathinfo($path, PATHINFO_EXTENSION)] ?? self::FALLBACK_MEDIA_TYPE;
    }

    /**
     * @throws RuntimeException If the generated file cannot be opened.
     */
    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $match = [];
        if (1 !== preg_match(self::PATH_PATTERN, $request->getUri()->getPath(), $match)) {
            return $this->responses->createResponse(404);
        }

        /** @var array{folder: string, name: string, filename: string} $match Every named group takes part in a match. */

        $path = $this->generator->generate(
            rawurldecode($match['folder']),
            $match['name'],
            rawurldecode($match['filename']),
        );
        if (null === $path || ! is_file($path)) {
            return $this->responses->createResponse(404);
        }

        $body     = $this->streams->createStreamFromFile($path, 'rb');
        $response = $this->responses
            ->createResponse(200)
            ->withHeader('Content-Type', self::mediaType($path))
            ->withHeader('Cache-Control', self::CACHE_CONTROL)
            ->withBody($body);

        $size = $body->getSize();

        return null === $size ? $response : $response->withHeader('Content-Length', sprintf('%d', $size));
    }
}
