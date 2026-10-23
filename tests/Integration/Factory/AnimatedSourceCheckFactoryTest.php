<?php

declare(strict_types=1);

namespace Contenir\Asset\Mezzio\Tests\Integration\Factory;

use Contenir\Asset\Mezzio\Service\AnimatedSourceCheck;
use Contenir\Asset\Mezzio\Service\AssetUrlBuilder;
use Contenir\Asset\Mezzio\Service\Factory\AnimatedSourceCheckFactory;
use Contenir\Asset\Mezzio\Tests\TestAsset\Container\InMemoryContainer;
use Contenir\Asset\Mezzio\Tests\TestAsset\Image\GifFactory;
use Contenir\Asset\Mezzio\Tests\Trait\TemporaryDirectoryTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The factory points the check at the primary backend's web root, inside a
 * scratch directory that is also the working directory.
 */
#[Group('integration')]
final class AnimatedSourceCheckFactoryTest extends TestCase
{
    use TemporaryDirectoryTrait;

    #[Test]
    public function leavesAnObjectStoreUninspected(): void
    {
        $this->writeFile('web/asset/spin.gif', GifFactory::bytes(2));

        static::assertFalse($this->check([
            'r2' => ['type' => 's3', 'default' => true, 'root_path' => $this->path('web')],
        ])->isAnimated('/asset/spin.gif'));
    }

    #[Test]
    public function readsALocalBackendNamedByItsType(): void
    {
        $this->writeFile('web/asset/spin.gif', GifFactory::bytes(2));

        static::assertTrue($this->check([
            'disk' => ['type' => 'local', 'default' => true, 'root_path' => $this->path('web')],
        ])->isAnimated('/asset/spin.gif'));
    }

    #[Test]
    public function readsUnderPublicWhenNoRootPathIsConfigured(): void
    {
        $this->writeFile('public/asset/spin.gif', GifFactory::bytes(2));

        static::assertTrue($this->check([])->isAnimated('/asset/spin.gif'));
    }

    #[Test]
    public function readsUnderTheConfiguredRootPath(): void
    {
        $this->writeFile('web/asset/spin.gif', GifFactory::bytes(2));

        static::assertTrue($this->check(['local' => ['root_path' => $this->path('web')]])->isAnimated(
            '/asset/spin.gif',
        ));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTemporaryDirectory();
    }

    protected function tearDown(): void
    {
        $this->tearDownTemporaryDirectory();
        parent::tearDown();
    }

    /**
     * @param array<string, array<string, mixed>> $backends
     */
    private function check(array $backends): AnimatedSourceCheck
    {
        return (new AnimatedSourceCheckFactory())(new InMemoryContainer([
            'config'               => [] === $backends ? [] : ['storage' => ['backend' => $backends]],
            AssetUrlBuilder::class => new AssetUrlBuilder(''),
        ]));
    }
}
