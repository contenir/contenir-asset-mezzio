<?php

declare(strict_types=1);

namespace Contenir\Asset\Mezzio\Tests\Unit\View;

use Contenir\Asset\Mezzio\View\SiteAssets;
use Contenir\Asset\Mezzio\View\SiteAssetsOptions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class SiteAssetsTest extends TestCase
{
    /**
     * @return array<string, array{?string, string}>
     */
    public static function themeProvider(): array
    {
        return [
            'no theme'                 => [
                null,
                "<link rel=\"icon\" href=\"/favicon.png\">\n<meta name=\"theme-color\" content=\"#fff\">",
            ],
            'an empty theme'           => [
                '',
                "<link rel=\"icon\" href=\"/favicon.png\">\n<meta name=\"theme-color\" content=\"#fff\">",
            ],
            'the default theme'        => [
                'default',
                "<link rel=\"icon\" href=\"/favicon.png\">\n<meta name=\"theme-color\" content=\"#fff\">",
            ],
            'a theme'                  => [
                'copper',
                "<link rel=\"icon\" href=\"/favicon-copper.png\">\n<meta name=\"theme-color\" content=\"#b87333\">",
            ],
            'a theme without a colour' => [
                'common',
                "<link rel=\"icon\" href=\"/favicon-common.png\">\n<meta name=\"theme-color\" content=\"#fff\">",
            ],
        ];
    }

    private static function assets(SiteAssetsOptions $options): SiteAssets
    {
        return new SiteAssets($options, '/nowhere');
    }

    #[Test]
    public function escapesWhatItWrites(): void
    {
        $assets = self::assets(new SiteAssetsOptions(
            stylesheets: ["css/a\"b'c.css"],
            attributes: ['data-x' => '<y>'],
            themeColors: ['default' => '"red"'],
        ));

        static::assertSame(
            '<link rel="stylesheet" href="/asset/css/a&quot;b&apos;c.css" data-x="&lt;y&gt;">'
                . "\n"
                . '<meta name="theme-color" content="&quot;red&quot;">',
            $assets->head(),
        );
    }

    #[Test]
    public function writesNothingWithoutSettings(): void
    {
        $assets = self::assets(new SiteAssetsOptions());

        static::assertSame(['', '', $assets], [$assets->head(), $assets->bodyEnd(), $assets()]);
    }

    #[Test]
    public function writesTheAssetsWithoutAVersionOrNonce(): void
    {
        $assets = self::assets(new SiteAssetsOptions(
            stylesheets: ['css/app.css'],
            scripts: ['js/app.js'],
            jsClass: true,
        ));

        static::assertSame(
            [
                '<link rel="stylesheet" href="/asset/css/app.css">'
                    . "\n"
                    . '<link rel="preload" href="/asset/js/app.js" as="script">'
                    . "\n"
                    . '<script>document.documentElement.classList.add("js")</script>',
                '<script src="/asset/js/app.js"></script>',
            ],
            [$assets->head(nonce: ''), $assets->bodyEnd()],
        );
    }

    #[Test]
    #[DataProvider('themeProvider')]
    public function writesTheIconsAndColourOfThePagesTheme(?string $theme, string $expected): void
    {
        $assets = self::assets(new SiteAssetsOptions(
            icons: [['rel' => 'icon', 'href' => '/favicon{theme}.png']],
            themeColors: ['default' => '#fff', 'copper' => '#b87333'],
        ));

        static::assertSame($expected, $assets->head($theme));
    }

    #[Test]
    public function writesTheStylesheetsPreloadsAndScriptAtTheirVersion(): void
    {
        $assets = self::assets(new SiteAssetsOptions(
            path: '/asset/',
            version: '1.1.9',
            stylesheets: ['css/app.css', '/top.css'],
            scripts: ['js/app.js'],
            attributes: ['data-router-track' => 'reload'],
            preloadFonts: ['/font/a.woff2'],
            jsClass: true,
        ));

        static::assertSame(
            [
                '<link rel="stylesheet" href="/asset/css/1.1.9/app.css" data-router-track="reload">'
                    . "\n"
                    . '<link rel="stylesheet" href="/asset/top.css" data-router-track="reload">'
                    . "\n"
                    . '<link rel="preload" href="/asset/font/a.woff2" as="font" type="font/woff2" crossorigin="anonymous">'
                    . "\n"
                    . '<link rel="preload" href="/asset/js/1.1.9/app.js" as="script">'
                    . "\n"
                    . '<script nonce="n">document.documentElement.classList.add("js")</script>',
                '<script src="/asset/js/1.1.9/app.js" data-router-track="reload" nonce="n"></script>',
            ],
            [$assets->head(nonce: 'n'), $assets->bodyEnd('n')],
        );
    }
}
