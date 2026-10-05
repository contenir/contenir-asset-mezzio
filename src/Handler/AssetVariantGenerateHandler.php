<?php

declare(strict_types=1);

namespace Contenir\Asset\Mezzio\Handler;

use Contenir\Asset\Mezzio\Security\PathGuard;
use Contenir\Asset\Mezzio\Service\OnDemandVariantResolver;
use Contenir\Storage\Exception\WriteException;
use JsonException;
use Override;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;
use SensitiveParameter;

use function hash_equals;
use function is_string;
use function json_encode;

use const JSON_THROW_ON_ERROR;

/**
 * Origin endpoint for the R2 edge miss-proxy: given a sibling variant key the
 * CDN/Worker failed to find, generate it back into the bucket and report its URL.
 *
 * Guarded by a shared secret (the primary backend's `generate_secret`) sent in
 * the `X-Asset-Generate-Secret` header, so only the Worker can trigger
 * generation. Every answer carries `Cache-Control: no-store`, so no cache
 * between the Worker and the origin keeps one.
 *
 * @api
 */
final class AssetVariantGenerateHandler implements RequestHandlerInterface
{
    public const string SECRET_HEADER = 'X-Asset-Generate-Secret';

    private const string CACHE_CONTROL = 'no-store';

    public function __construct(
        private readonly OnDemandVariantResolver $resolver,
        private readonly ResponseFactoryInterface $responses,
        private readonly StreamFactoryInterface $streams,
        #[SensitiveParameter]
        private readonly string $secret,
    ) {}

    /**
     * @throws WriteException If the owning backend cannot generate or store the variant.
     * @throws JsonException  If the generated URL is not valid UTF-8.
     *
     * @mago-expect analysis:mixed-assignment Query parameters are untyped request input; checked here.
     */
    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if ('' === $this->secret) {
            return $this->json(503, ['error' => 'generation endpoint not configured']);
        }

        if (! hash_equals($this->secret, $request->getHeaderLine(self::SECRET_HEADER))) {
            return $this->empty(403);
        }

        $key = $request->getQueryParams()['key'] ?? '';
        if (! is_string($key) || '' === $key) {
            return $this->json(400, ['error' => 'missing key']);
        }

        /**
         * The key names an object in the bucket; one that could climb out of
         * its directory is refused here, before any backend sees it.
         */
        if (! PathGuard::isSafePath($key)) {
            return $this->empty(404);
        }

        $url = $this->resolver->generate($key);
        if (null === $url) {
            return $this->empty(404);
        }

        return $this->json(200, ['url' => $url]);
    }

    private function empty(int $status): ResponseInterface
    {
        return $this->responses->createResponse($status)->withHeader('Cache-Control', self::CACHE_CONTROL);
    }

    /**
     * @param array<string, string> $data
     */
    private function json(int $status, array $data): ResponseInterface
    {
        return $this->empty($status)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->streams->createStream(json_encode($data, flags: JSON_THROW_ON_ERROR)));
    }
}
