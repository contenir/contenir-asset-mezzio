<?php

declare(strict_types=1);

namespace Contenir\Asset\Mezzio\Tests\Integration;

use Contenir\Asset\Mezzio\Command\VariantsCommand;
use Contenir\Asset\Mezzio\Handler\AssetVariantGenerateHandler;
use Contenir\Asset\Mezzio\Handler\AssetVariantHandler;
use Contenir\Asset\Mezzio\Tests\Trait\MezzioContainerTrait;
use Contenir\Asset\Mezzio\View\StorageUrl;
use Contenir\Storage\Image\ImageResizer;
use Contenir\Storage\Image\ImageResizerInterface;
use Contenir\Storage\StorageManager;
use Laminas\ServiceManager\ServiceManager;
use Laminas\View\HelperPluginManager;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Resolves the package's services through a real laminas-servicemanager
 * container and its template services through laminas-view's
 * HelperPluginManager, the way mezzio-laminasviewrenderer builds it.
 */
#[Group('integration')]
final class ContainerWiringTest extends TestCase
{
    use MezzioContainerTrait;

    private ServiceManager $container;

    #[Test]
    public function handlersAndTheCommandResolve(): void
    {
        static::assertInstanceOf(AssetVariantHandler::class, $this->container->get(AssetVariantHandler::class));
        static::assertInstanceOf(
            AssetVariantGenerateHandler::class,
            $this->container->get(AssetVariantGenerateHandler::class),
        );
        static::assertInstanceOf(VariantsCommand::class, $this->container->get(VariantsCommand::class));
    }

    #[Test]
    public function resizerInterfaceResolvesToTheSharedResizer(): void
    {
        static::assertSame(
            $this->container->get(ImageResizer::class),
            $this->container->get(ImageResizerInterface::class),
        );
    }

    #[Test]
    public function templateServicesAreSharedWithTheContainer(): void
    {
        $url = $this->container->get(StorageUrl::class);

        static::assertInstanceOf(StorageUrl::class, $url);
        static::assertSame('/media/news/a.jpg', $url('news/a.jpg'));
    }

    #[Test]
    public function viewHelpersRenderThroughTheHelperPluginManager(): void
    {
        /** @var array{view_helpers: array<string, mixed>} $config */
        $config  = $this->container->get('config');
        $helpers = new HelperPluginManager($this->container, $config['view_helpers']);

        $srcSet  = $helpers->get('storageSrcSet');
        $sizes   = $helpers->get('storageSizes');
        $sources = $helpers->get('StorageSources');
        $url     = $helpers->get('storageUrl');

        static::assertSame(
            [
                '/media/news/_variant/card-320/a.jpg 320w',
                '100vw',
                '<source type="image/webp" srcset="/media/news/_variant/card-320/a.webp 320w" sizes="100vw">',
                '/media/news/_variant/card-320/a.avif',
            ],
            [
                $srcSet('/media/news/a.jpg', profile: 'card'),
                $sizes('card'),
                $sources('news/a.jpg', profile: 'card'),
                $url('news/a.jpg', variant: 'card-320', format: 'avif'),
            ],
        );
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->container = $this->mezzioContainer([
            'storage'      => [
                'backend'  => ['local' => [
                    'type'        => 'local',
                    'public_path' => '/media',
                    'binary'      => '/bin/false',
                ]],
                'variants' => ['card' => ['dimensions' => ['320x'], 'sizes' => '100vw', 'formats' => ['webp']]],
            ],
            'dependencies' => [
                'services' => [StorageManager::class => new StorageManager()],
            ],
        ]);
    }
}
