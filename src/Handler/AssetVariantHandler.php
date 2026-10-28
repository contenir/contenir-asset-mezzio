<?php

declare(strict_types=1);

namespace Contenir\Asset\Mezzio\Handler;

use Contenir\Asset\Mezzio\Service\AssetRedirects;
use Contenir\Asset\Mezzio\Service\VariantGenerator;
use Override;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;

use function dirname;
use function is_file;
use function pathinfo;
use function preg_match;
use function rawurldecode;
use function sprintf;
use function str_starts_with;
use function strlen;
use function substr;

use const PATHINFO_EXTENSION;
use const PATHINFO_FILENAME;

/**
 * Serves keyed image variants on demand under
 * `/asset/<folder>/_variant/<name>/<filename>`.
 *
 * Existing variant files are served directly by the web server; only missing
 * ones reach this handler, which materialises them via {@see VariantGenerator}
 * and answers with the file as the response body, or an empty 404 when the
 * variant cannot be produced. A file the CMS moved or renamed is answered with a 301 to where it now
 * is ({@see AssetRedirects}), its variants to the same variant there.
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

    private const string PREFIX = '/asset/';

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
        private readonly ?AssetRedirects $redirects = null,
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
            return $this->movedOrMissing($request->getUri()->getPath());
        }

        /** @var array{folder: string, name: string, filename: string} $match Every named group takes part in a match. */

        $path = $this->generator->generate(
            rawurldecode($match['folder']),
            $match['name'],
            rawurldecode($match['filename']),
        );
        if (null === $path || ! is_file($path)) {
            return $this->movedVariantOrMissing($match);
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

    /**
     * A redirect to where a moved file now is, or 404.
     */
    private function movedOrMissing(string $requested): ResponseInterface
    {
        $to = str_starts_with($requested, self::PREFIX)
            ? $this->redirects?->of(rawurldecode(substr($requested, strlen(self::PREFIX))))
            : null;

        return null === $to ? $this->responses->createResponse(404) : $this->redirect(self::PREFIX . $to);
    }

    /**
     * A redirect to the same variant of the file a variant was made of, where it now is, or 404.
     *
     * @param array{folder: string, name: string, filename: string} $match
     */
    private function movedVariantOrMissing(array $match): ResponseInterface
    {
        $filename = rawurldecode($match['filename']);
        $to       = $this->redirects?->ofVariant(rawurldecode($match['folder']), $filename);
        if (null === $to) {
            return $this->responses->createResponse(404);
        }

        $extension = pathinfo($filename, PATHINFO_EXTENSION);

        return $this->redirect(sprintf(
            '%s%s/_variant/%s/%s%s',
            self::PREFIX,
            dirname($to),
            $match['name'],
            pathinfo($to, PATHINFO_FILENAME),
            '' === $extension ? '' : ".{$extension}",
        ));
    }

    private function redirect(string $location): ResponseInterface
    {
        return $this->responses
            ->createResponse(301)
            ->withHeader('Location', $location)
            ->withHeader('Cache-Control', self::CACHE_CONTROL);
    }
}
