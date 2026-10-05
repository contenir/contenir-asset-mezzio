<?php

declare(strict_types=1);

namespace Contenir\Asset\Mezzio\Tests\Unit;

use Contenir\Asset\Mezzio\Command\VariantsCommand;
use Contenir\Asset\Mezzio\Command\VariantsCommandFactory;
use Contenir\Asset\Mezzio\ConfigProvider;
use Contenir\Asset\Mezzio\Handler\AssetVariantGenerateHandler;
use Contenir\Asset\Mezzio\Handler\AssetVariantHandler;
use Contenir\Asset\Mezzio\Handler\Factory\AssetVariantGenerateHandlerFactory;
use Contenir\Asset\Mezzio\Handler\Factory\AssetVariantHandlerFactory;
use Contenir\Asset\Mezzio\Service\AssetUrlBuilder;
use Contenir\Asset\Mezzio\Service\Factory\AssetUrlBuilderFactory;
use Contenir\Asset\Mezzio\Service\Factory\ImageResizerFactory;
use Contenir\Asset\Mezzio\Service\Factory\OnDemandVariantResolverFactory;
use Contenir\Asset\Mezzio\Service\Factory\ProfileProviderServiceFactory;
use Contenir\Asset\Mezzio\Service\Factory\VariantGeneratorFactory;
use Contenir\Asset\Mezzio\Service\OnDemandVariantResolver;
use Contenir\Asset\Mezzio\Service\ProfileProviderService;
use Contenir\Asset\Mezzio\Service\VariantGenerator;
use Contenir\Asset\Mezzio\View\Factory\StorageSizesFactory;
use Contenir\Asset\Mezzio\View\Factory\StorageSourcesFactory;
use Contenir\Asset\Mezzio\View\Factory\StorageSrcSetFactory;
use Contenir\Asset\Mezzio\View\Factory\StorageUrlFactory;
use Contenir\Asset\Mezzio\View\StorageSizes;
use Contenir\Asset\Mezzio\View\StorageSources;
use Contenir\Asset\Mezzio\View\StorageSrcSet;
use Contenir\Asset\Mezzio\View\StorageUrl;
use Contenir\Storage\Image\ImageResizer;
use Contenir\Storage\Image\ImageResizerInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class ConfigProviderTest extends TestCase
{
    #[Test]
    public function composesEachSection(): void
    {
        $provider = new ConfigProvider();

        static::assertSame(
            [
                'dependencies' => $provider->getDependencies(),
                'view_helpers' => $provider->getViewHelperConfig(),
                'laminas-cli'  => $provider->getCliConfig(),
            ],
            $provider(),
        );
    }

    #[Test]
    public function registersEveryService(): void
    {
        static::assertSame(
            [
                'aliases'   => [ImageResizerInterface::class => ImageResizer::class],
                'factories' => [
                    VariantsCommand::class             => VariantsCommandFactory::class,
                    AssetVariantGenerateHandler::class => AssetVariantGenerateHandlerFactory::class,
                    AssetVariantHandler::class         => AssetVariantHandlerFactory::class,
                    ImageResizer::class                => ImageResizerFactory::class,
                    AssetUrlBuilder::class             => AssetUrlBuilderFactory::class,
                    OnDemandVariantResolver::class     => OnDemandVariantResolverFactory::class,
                    ProfileProviderService::class      => ProfileProviderServiceFactory::class,
                    VariantGenerator::class            => VariantGeneratorFactory::class,
                    StorageSizes::class                => StorageSizesFactory::class,
                    StorageSources::class              => StorageSourcesFactory::class,
                    StorageSrcSet::class               => StorageSrcSetFactory::class,
                    StorageUrl::class                  => StorageUrlFactory::class,
                ],
            ],
            (new ConfigProvider())->getDependencies(),
        );
    }

    #[Test]
    public function registersTheVariantsCommand(): void
    {
        static::assertSame(
            ['commands' => ['storage:variants' => VariantsCommand::class]],
            (new ConfigProvider())->getCliConfig(),
        );
    }

    #[Test]
    public function registersTheViewHelpersUnderBothCasings(): void
    {
        static::assertSame(
            [
                'aliases'   => [
                    'storageSizes'   => StorageSizes::class,
                    'StorageSizes'   => StorageSizes::class,
                    'storageSources' => StorageSources::class,
                    'StorageSources' => StorageSources::class,
                    'storageSrcSet'  => StorageSrcSet::class,
                    'StorageSrcSet'  => StorageSrcSet::class,
                    'storageUrl'     => StorageUrl::class,
                    'StorageUrl'     => StorageUrl::class,
                ],
                'factories' => [
                    StorageSizes::class   => StorageSizesFactory::class,
                    StorageSources::class => StorageSourcesFactory::class,
                    StorageSrcSet::class  => StorageSrcSetFactory::class,
                    StorageUrl::class     => StorageUrlFactory::class,
                ],
            ],
            (new ConfigProvider())->getViewHelperConfig(),
        );
    }
}
