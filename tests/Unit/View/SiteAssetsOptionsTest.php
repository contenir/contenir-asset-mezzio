<?php

declare(strict_types=1);

namespace Contenir\Asset\Mezzio\Tests\Unit\View;

use Contenir\Asset\Mezzio\View\SiteAssetsOptions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class SiteAssetsOptionsTest extends TestCase
{
    /**
     * @return array<string, array{array<array-key, mixed>}>
     */
    public static function wrongTypesProvider(): array
    {
        return [
            'nothing'     => [[]],
            'wrong types' => [[
                'path'          => [],
                'version'       => [],
                'stylesheets'   => 7,
                'scripts'       => [7, ''],
                'attributes'    => ['data-x' => 7, 3 => 'y'],
                'preload_fonts' => '',
                'inline_css'    => null,
                'icons'         => [['rel' => 'icon'], 'not an icon'],
                'theme_colors'  => 'red',
                'js_class'      => 'yes',
            ]],
        ];
    }

    #[Test]
    public function keepsTheValuesOfTheRightTypeAmongTheWrongOnes(): void
    {
        $options = SiteAssetsOptions::fromArray([
            'scripts'      => [7, 'js/app.js'],
            'theme_colors' => [3 => 'red', 'default' => '#fff'],
            'icons'        => [['rel' => 'icon'], ['rel' => 'icon', 'href' => '/a.png']],
        ]);

        static::assertSame(
            [['js/app.js'], ['default' => '#fff'], [['rel' => 'icon', 'href' => '/a.png']]],
            [$options->scripts, $options->themeColors, $options->icons],
        );
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Test]
    #[DataProvider('wrongTypesProvider')]
    public function leavesOutValuesOfTheWrongType(array $config): void
    {
        static::assertEquals(new SiteAssetsOptions(), SiteAssetsOptions::fromArray($config));
    }

    #[Test]
    public function readsEverySetting(): void
    {
        static::assertEquals(
            new SiteAssetsOptions(
                path: '/static',
                version: '12',
                stylesheets: ['css/app.css', 'css/print.css'],
                scripts: ['js/app.js'],
                attributes: ['data-router-track' => 'reload'],
                preloadFonts: ['font/a.woff2'],
                inlineCss: ['css/inline.css'],
                icons: [['rel' => 'icon', 'href' => '/favicon{theme}.png']],
                themeColors: ['default' => '#fff'],
                jsClass: true,
            ),
            SiteAssetsOptions::fromArray([
                'path'          => '/static',
                'version'       => 12,
                'stylesheets'   => ['css/app.css', 'css/print.css'],
                'scripts'       => 'js/app.js',
                'attributes'    => ['data-router-track' => 'reload'],
                'preload_fonts' => ['font/a.woff2'],
                'inline_css'    => 'css/inline.css',
                'icons'         => [['rel' => 'icon', 'href' => '/favicon{theme}.png']],
                'theme_colors'  => ['default' => '#fff'],
                'js_class'      => true,
            ]),
        );
    }
}
