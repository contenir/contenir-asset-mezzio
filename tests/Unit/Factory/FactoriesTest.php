<?php

declare(strict_types=1);

namespace Contenir\Asset\Mezzio\Tests\Unit\Factory;

use Contenir\Asset\Mezzio\Command\VariantsCommand;
use Contenir\Asset\Mezzio\Command\VariantsCommandFactory;
use Contenir\Asset\Mezzio\Handler\AssetVariantGenerateHandler;
use Contenir\Asset\Mezzio\Handler\AssetVariantHandler;
use Contenir\Asset\Mezzio\Handler\Factory\AssetVariantGenerateHandlerFactory;
use Contenir\Asset\Mezzio\Handler\Factory\AssetVariantHandlerFactory;
use Contenir\Asset\Mezzio\Service\AssetUrlBuilder;
use Contenir\Asset\Mezzio\Service\Factory\AssetUrlBuilderFactory;
use Contenir\Asset\Mezzio\Service\Factory\ImageResizerFactory;
use Contenir\Asset\Mezzio\Service\Factory\OnDemandVariantResolverFactory;
use Contenir\Asset\Mezzio\Service\Factory\ProfileProviderServiceFactory;
use Contenir\Asset\Mezzio\Service\OnDemandVariantResolver;
use Contenir\Asset\Mezzio\Service\ProfileProviderService;
use Contenir\Asset\Mezzio\Service\VariantGenerator;
use Contenir\Asset\Mezzio\Tests\TestAsset\Container\InMemoryContainer;
use Contenir\Asset\Mezzio\Tests\TestAsset\Log\RecordingLogger;
use Contenir\Asset\Mezzio\View\Factory\StorageSizesFactory;
use Contenir\Asset\Mezzio\View\Factory\StorageSourcesFactory;
use Contenir\Asset\Mezzio\View\Factory\StorageSrcSetFactory;
use Contenir\Asset\Mezzio\View\Factory\StorageUrlFactory;
use Contenir\Asset\Mezzio\View\StorageSizes;
use Contenir\Asset\Mezzio\View\StorageSources;
use Contenir\Asset\Mezzio\View\StorageSrcSet;
use Contenir\Asset\Mezzio\View\StorageUrl;
use Contenir\Storage\Image\ImageResizerInterface;
use Contenir\Storage\Image\StubImageResizer;
use Contenir\Storage\StorageManager;
use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\StreamFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * @mago-expect lint:no-literal-password Dummy shared secrets.
 */
#[Group('unit')]
final class FactoriesTest extends TestCase
{
    /**
     * @return array<string, array{callable(InMemoryContainer): object}>
     */
    public static function loggingHelperProvider(): array
    {
        return [
            'storageSrcSet'  => [
                static fn(InMemoryContainer $c): string => (new StorageSrcSetFactory())($c)('a.jpg', profile: 'nope'),
            ],
            'storageSources' => [
                static fn(InMemoryContainer $c): string => (new StorageSourcesFactory())($c)('a.jpg', profile: 'nope'),
            ],
            'storageUrl'     => [
                static fn(InMemoryContainer $c): string => (new StorageUrlFactory())($c)('a.jpg', variant: 'nope'),
            ],
        ];
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function urlBuilderProvider(): array
    {
        return [
            'local, no config'      => [[], '/a/_variant/thumb/b.jpg'],
            'local public path'     => [
                ['local' => ['type' => 'local', 'public_path' => '/media']],
                '/media/a/_variant/thumb/b.jpg',
            ],
            's3 public_base_url'    => [
                ['r2' => ['type' => 's3', 'default' => true, 'public_base_url' => 'https://cdn.test']],
                'https://cdn.test/a/b__thumb.jpg',
            ],
            's3 both bases'         => [
                ['r2' => [
                    'type'            => 's3',
                    'default'         => true,
                    'public_base_url' => 'https://primary.test',
                    'publicUrl'       => 'https://alias.test',
                ]],
                'https://primary.test/a/b__thumb.jpg',
            ],
            's3 publicUrl fallback' => [
                ['r2' => ['type' => 's3', 'default' => true, 'publicUrl' => 'https://cdn.test']],
                'https://cdn.test/a/b__thumb.jpg',
            ],
            's3 without a base'     => [['r2' => ['type' => 's3', 'default' => true]], '/a/b__thumb.jpg'],
        ];
    }

    #[Test]
    public function generateHandlerIsGuardedByTheConfiguredSecret(): void
    {
        $handler = (new AssetVariantGenerateHandlerFactory())($this->services([
            'config' => ['storage' => ['backend' => ['local' => ['generate_secret' => 's']]]],
        ]));

        static::assertSame(403, $handler->handle(new ServerRequest())->getStatusCode());
    }

    #[Test]
    public function generateHandlerIsUnavailableWithoutAConfiguredSecret(): void
    {
        $handler = (new AssetVariantGenerateHandlerFactory())($this->services(['config' => []]));

        static::assertSame(503, $handler->handle(new ServerRequest())->getStatusCode());
    }

    /**
     * @param callable(InMemoryContainer): string $render
     */
    #[Test]
    #[DataProvider('loggingHelperProvider')]
    public function helpersLogThroughTheApplicationLogger(callable $render): void
    {
        $logger = new RecordingLogger();

        $render($this->services([LoggerInterface::class => $logger]));

        static::assertCount(1, $logger->records);
    }

    #[Test]
    public function imageResizerUsesTheConfiguredBinary(): void
    {
        $resizer = (new ImageResizerFactory())(new InMemoryContainer([
            'config' => ['storage' => ['backend' => ['local' => ['binary' => '/opt/magick']]]],
        ]));

        static::assertSame('/opt/magick', $resizer->binaryPath());
    }

    #[Test]
    public function profileProviderReadsStorageVariants(): void
    {
        $provider = (new ProfileProviderServiceFactory())(new InMemoryContainer([
            'config' => ['storage' => ['variants' => ['admin-thumb' => ['width' => 180, 'height' => 180]]]],
        ]));

        static::assertSame(180, $provider->variant('admin-thumb')?->width);
    }

    #[Test]
    public function servicesAreBuiltFromTheirDependencies(): void
    {
        $container = $this->services();

        static::assertInstanceOf(VariantsCommand::class, (new VariantsCommandFactory())($container));
        static::assertInstanceOf(AssetVariantHandler::class, (new AssetVariantHandlerFactory())($container));
        static::assertInstanceOf(
            AssetVariantGenerateHandler::class,
            (new AssetVariantGenerateHandlerFactory())($container),
        );
        static::assertInstanceOf(OnDemandVariantResolver::class, (new OnDemandVariantResolverFactory())($container));
        static::assertInstanceOf(StorageSizes::class, (new StorageSizesFactory())($container));
        static::assertInstanceOf(StorageSources::class, (new StorageSourcesFactory())($container));
        static::assertInstanceOf(StorageSrcSet::class, (new StorageSrcSetFactory())($container));
        static::assertInstanceOf(StorageUrl::class, (new StorageUrlFactory())($container));
    }

    #[Test]
    public function sizesHelperReadsTheProfiles(): void
    {
        $helper = (new StorageSizesFactory())($this->services([
            ProfileProviderService::class => new ProfileProviderService([
                'card' => ['dimensions' => ['320x'], 'sizes' => '100vw'],
            ]),
        ]));

        static::assertSame('100vw', $helper('card'));
    }

    /**
     * @param array<string, mixed> $backend
     */
    #[Test]
    #[DataProvider('urlBuilderProvider')]
    public function urlBuilderFollowsThePrimaryBackendScheme(array $backend, string $expected): void
    {
        $builder = (new AssetUrlBuilderFactory())(new InMemoryContainer([
            'config' => ['storage' => ['backend' => $backend]],
        ]));

        static::assertSame($expected, $builder->variantUrl('a/b.jpg', 'thumb'));
    }

    /**
     * Every service the factories fetch, with $overrides on top.
     *
     * @param array<string, mixed> $overrides
     */
    private function services(array $overrides = []): InMemoryContainer
    {
        $profiles = new ProfileProviderService(['thumb' => ['width' => 10]]);
        $manager  = new StorageManager();

        return new InMemoryContainer([
            'config'                        => ['storage' => ['backend' => ['local' => ['generate_secret' => 's']]]],
            ProfileProviderService::class   => $profiles,
            AssetUrlBuilder::class          => new AssetUrlBuilder(''),
            StorageManager::class           => $manager,
            OnDemandVariantResolver::class  => new OnDemandVariantResolver($manager),
            VariantGenerator::class         => new VariantGenerator(new StubImageResizer(), $profiles, '/var/www'),
            ImageResizerInterface::class    => new StubImageResizer(),
            ResponseFactoryInterface::class => new ResponseFactory(),
            StreamFactoryInterface::class   => new StreamFactory(),
            ...$overrides,
        ]);
    }
}
