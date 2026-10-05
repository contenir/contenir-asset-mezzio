<?php

declare(strict_types=1);

namespace Contenir\Asset\Mezzio\Tests\Unit\Handler;

use Contenir\Asset\Mezzio\Handler\AssetVariantGenerateHandler;
use Contenir\Asset\Mezzio\Service\OnDemandVariantResolver;
use Contenir\Asset\Mezzio\Tests\TestAsset\Storage\FakeOnDemandStorage;
use Contenir\Storage\StorageManager;
use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\StreamFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use SensitiveParameter;

#[Group('unit')]
#[Group('handler')]
final class AssetVariantGenerateHandlerTest extends TestCase
{
    /**
     * @return array<string, array{mixed}>
     */
    public static function missingKeyProvider(): array
    {
        return [
            'absent'     => [null],
            'empty'      => [''],
            'not string' => [['a.jpg']],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unsafeKeyProvider(): array
    {
        return [
            'parent segment'       => ['../secrets/a__thumb.jpg'],
            'inner parent segment' => ['gallery/../../a__thumb.jpg'],
            'null byte'            => ["a__thumb.jpg\0"],
            'encoded parent'       => ['%2e%2e/a__thumb.jpg'],
            'backslash'            => ['..\\a__thumb.jpg'],
        ];
    }

    #[Test]
    public function answersNotFoundWhenNoBackendCanGenerate(): void
    {
        $response = $this->handle('s3cret', $this->request('s3cret', 'a__thumb.jpg'), new StorageManager());

        static::assertSame([404, ['Cache-Control' => ['no-store']], ''], $this->summary($response));
    }

    #[Test]
    public function isUnavailableWithoutAConfiguredSecret(): void
    {
        $response = $this->handle('', $this->request('', 'a__thumb.jpg'));

        static::assertSame(
            [
                503,
                ['Cache-Control' => ['no-store'], 'Content-Type' => ['application/json']],
                '{"error":"generation endpoint not configured"}',
            ],
            $this->summary($response),
        );
    }

    #[Test]
    #[DataProvider('unsafeKeyProvider')]
    public function refusesAKeyThatClimbsOutOfItsDirectory(string $key): void
    {
        $storage = new FakeOnDemandStorage([]);
        $manager = new StorageManager();
        $manager->register('r2', $storage);

        $response = $this->handle('s3cret', $this->request('s3cret', $key), $manager);

        static::assertSame(404, $response->getStatusCode());
        static::assertSame([], $storage->generated);
    }

    #[Test]
    #[DataProvider('missingKeyProvider')]
    public function rejectsAMissingKey(mixed $key): void
    {
        $request = (new ServerRequest())->withHeader(AssetVariantGenerateHandler::SECRET_HEADER, 's3cret')
            ->withQueryParams(null === $key ? [] : ['key' => $key]);

        static::assertSame(
            [400, ['Cache-Control' => ['no-store'], 'Content-Type' => ['application/json']], '{"error":"missing key"}'],
            $this->summary($this->handle('s3cret', $request)),
        );
    }

    #[Test]
    public function rejectsAMissingSecret(): void
    {
        $response = $this->handle('s3cret', (new ServerRequest())->withQueryParams(['key' => 'a__thumb.jpg']));

        static::assertSame([403, ['Cache-Control' => ['no-store']], ''], $this->summary($response));
    }

    #[Test]
    public function rejectsAWrongSecret(): void
    {
        static::assertSame(403, $this->handle('s3cret', $this->request('wrong', 'k.jpg'))->getStatusCode());
    }

    #[Test]
    public function reportsTheUrlOfTheGeneratedVariant(): void
    {
        $response = $this->handle('s3cret', $this->request('s3cret', 'a__thumb.jpg'));

        static::assertSame(
            [
                200,
                ['Cache-Control' => ['no-store'], 'Content-Type' => ['application/json']],
                '{"url":"https:\/\/cdn.test\/a__thumb.jpg"}',
            ],
            $this->summary($response),
        );
    }

    private function handle(
        #[SensitiveParameter]
        string $secret,
        ServerRequest $request,
        ?StorageManager $manager = null,
    ): ResponseInterface {
        if (null === $manager) {
            $manager = new StorageManager();
            $manager->register('r2', new FakeOnDemandStorage([]));
        }

        $handler = new AssetVariantGenerateHandler(
            new OnDemandVariantResolver($manager),
            new ResponseFactory(),
            new StreamFactory(),
            $secret,
        );

        return $handler->handle($request);
    }

    private function request(#[SensitiveParameter] string $secret, string $key): ServerRequest
    {
        return (new ServerRequest())->withHeader(AssetVariantGenerateHandler::SECRET_HEADER, $secret)
            ->withQueryParams(['key' => $key]);
    }

    /**
     * @return array{int, array<string, list<string>>, string}
     */
    private function summary(ResponseInterface $response): array
    {
        /** @var array<string, list<string>> $headers */
        $headers = $response->getHeaders();

        return [$response->getStatusCode(), $headers, (string) $response->getBody()];
    }
}
