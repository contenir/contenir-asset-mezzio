<?php

declare(strict_types=1);

namespace Contenir\Asset\Mezzio\Tests\Integration\View;

use Contenir\Asset\Mezzio\Tests\TestAsset\Container\InMemoryContainer;
use Contenir\Asset\Mezzio\Tests\Trait\TemporaryDirectoryTrait;
use Contenir\Asset\Mezzio\View\Factory\SiteAssetsFactory;
use Contenir\Asset\Mezzio\View\SiteAssets;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The factory reads the site_assets block, and the inline CSS from the public
 * directory, inside a scratch directory that is also the working directory.
 */
#[Group('integration')]
final class SiteAssetsFactoryTest extends TestCase
{
    use TemporaryDirectoryTrait;

    /**
     * @return array<string, array{mixed}>
     */
    public static function noSettingsProvider(): array
    {
        return [
            'no config'          => [null],
            'no block'           => [[]],
            'a block of strings' => [['site_assets' => 'css/app.css']],
        ];
    }

    private static function assets(mixed $config): SiteAssets
    {
        return (new SiteAssetsFactory())(new InMemoryContainer(['config' => $config]));
    }

    #[Test]
    public function aDirectoryIsNoInlineCssFile(): void
    {
        $this->writeFile('public/asset/css/inline.css/x', 'p {}');

        static::assertSame('', self::assets(['site_assets' => ['inline_css' => 'css/inline.css']])->head());
    }

    #[Test]
    public function inlinesEachCssFileWithContentsFromThePublicDirectory(): void
    {
        $this->writeFile('web/asset/css/inline.css', "body { margin: 0 }\n\n");
        $this->writeFile('web/asset/css/more.css', '.a { color: red }');
        $this->writeFile('web/asset/css/blank.css', "  \n");

        static::assertSame(
            "<style nonce=\"n\">\nbody { margin: 0 }\n.a { color: red }\n</style>",
            self::assets([
                'site_assets' => [
                    'public_directory' => $this->path('web/'),
                    'inline_css'       => ['css/inline.css', 'css/missing.css', 'css/blank.css', 'css/more.css'],
                ],
            ])->head(nonce: 'n'),
        );
    }

    #[Test]
    public function readsTheInlineCssFromTheApplicationsPublicDirectoryByDefault(): void
    {
        $this->writeFile('public/asset/css/inline.css', 'p { margin: 0 }');

        static::assertSame(
            "<style>\np { margin: 0 }\n</style>",
            self::assets(['site_assets' => ['inline_css' => 'css/inline.css', 'public_directory' => '']])->head(),
        );
    }

    #[Test]
    #[DataProvider('noSettingsProvider')]
    public function writesNothingWithoutSettings(mixed $config): void
    {
        static::assertSame('', self::assets($config)->head());
    }

    protected function setUp(): void
    {
        $this->setUpTemporaryDirectory();
    }

    protected function tearDown(): void
    {
        $this->tearDownTemporaryDirectory();
    }
}
