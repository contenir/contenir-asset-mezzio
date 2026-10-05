<?php

declare(strict_types=1);

namespace Contenir\Asset\Mezzio;

use Contenir\Storage\Image\ImageResizer;
use Contenir\Storage\Image\ImageResizerInterface;

/**
 * Container wiring for the keyed asset bridge: the services, the two PSR-15
 * handlers, the template services, their laminas-view helper names and the
 * `storage:variants` laminas-cli command.
 *
 * Variant behaviour (widths, crop, quality, sizes, formats) is NOT defined
 * here: it lives in the shared `storage.variants` config that both this
 * package and the CMS read. Routes are not registered either; the
 * application routes the handlers in its own `config/routes.php` (see README).
 *
 * @api
 */
final readonly class ConfigProvider
{
    /**
     * laminas-cli command registration. Available on any consuming site as
     * `vendor/bin/laminas storage:variants`.
     *
     * @return array{commands: array<string, class-string>}
     */
    public function getCliConfig(): array
    {
        return [
            'commands' => [
                'storage:variants' => Command\VariantsCommand::class,
            ],
        ];
    }

    /**
     * @return array{aliases: array<class-string, class-string>, factories: array<class-string, class-string>}
     */
    public function getDependencies(): array
    {
        return [
            'aliases'   => [
                ImageResizerInterface::class => ImageResizer::class,
            ],
            'factories' => [
                Command\VariantsCommand::class             => Command\VariantsCommandFactory::class,
                Handler\AssetVariantGenerateHandler::class => Handler\Factory\AssetVariantGenerateHandlerFactory::class,
                Handler\AssetVariantHandler::class         => Handler\Factory\AssetVariantHandlerFactory::class,
                ImageResizer::class                        => Service\Factory\ImageResizerFactory::class,
                Service\AssetUrlBuilder::class             => Service\Factory\AssetUrlBuilderFactory::class,
                Service\OnDemandVariantResolver::class     => Service\Factory\OnDemandVariantResolverFactory::class,
                Service\ProfileProviderService::class      => Service\Factory\ProfileProviderServiceFactory::class,
                Service\VariantGenerator::class            => Service\Factory\VariantGeneratorFactory::class,
                View\StorageSizes::class                   => View\Factory\StorageSizesFactory::class,
                View\StorageSources::class                 => View\Factory\StorageSourcesFactory::class,
                View\StorageSrcSet::class                  => View\Factory\StorageSrcSetFactory::class,
                View\StorageUrl::class                     => View\Factory\StorageUrlFactory::class,
            ],
        ];
    }

    /**
     * laminas-view helper names for the template services. laminas-view's
     * HelperPluginManager accepts any callable, so the plain services are the
     * helpers; without laminas-view this block is inert config.
     *
     * @return array{aliases: array<string, class-string>, factories: array<class-string, class-string>}
     */
    public function getViewHelperConfig(): array
    {
        return [
            'aliases'   => [
                'storageSizes'   => View\StorageSizes::class,
                'StorageSizes'   => View\StorageSizes::class,
                'storageSources' => View\StorageSources::class,
                'StorageSources' => View\StorageSources::class,
                'storageSrcSet'  => View\StorageSrcSet::class,
                'StorageSrcSet'  => View\StorageSrcSet::class,
                'storageUrl'     => View\StorageUrl::class,
                'StorageUrl'     => View\StorageUrl::class,
            ],
            'factories' => [
                View\StorageSizes::class   => View\Factory\StorageSizesFactory::class,
                View\StorageSources::class => View\Factory\StorageSourcesFactory::class,
                View\StorageSrcSet::class  => View\Factory\StorageSrcSetFactory::class,
                View\StorageUrl::class     => View\Factory\StorageUrlFactory::class,
            ],
        ];
    }

    /**
     * @return array{
     *     dependencies: array{aliases: array<class-string, class-string>, factories: array<class-string, class-string>},
     *     view_helpers: array{aliases: array<string, class-string>, factories: array<class-string, class-string>},
     *     laminas-cli: array{commands: array<string, class-string>}
     * }
     */
    public function __invoke(): array
    {
        return [
            'dependencies' => $this->getDependencies(),
            'view_helpers' => $this->getViewHelperConfig(),
            'laminas-cli'  => $this->getCliConfig(),
        ];
    }
}
