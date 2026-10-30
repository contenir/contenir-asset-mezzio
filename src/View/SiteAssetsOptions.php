<?php

declare(strict_types=1);

namespace Contenir\Asset\Mezzio\View;

use function ltrim;
use function rtrim;
use function strpos;
use function substr;

/**
 * The site's own stylesheets, scripts, fonts and icons, from the `site_assets`
 * config block:
 *
 *     'site_assets' => [
 *         'path'          => '/asset',            // where the site's assets are served from
 *         'version'       => '1.1.9',             // /asset/css/1.1.9/app.css; none for /asset/css/app.css
 *         'stylesheets'   => ['css/app.css'],
 *         'scripts'       => ['js/app.js'],       // written at the end of the body, and preloaded
 *         'attributes'    => ['data-router-track' => 'reload'], // on each stylesheet and script
 *         'preload_fonts' => ['font/regular.woff2'],
 *         'inline_css'    => ['css/inline.css'],  // read from the public directory into a <style>
 *         'icons'         => [['rel' => 'icon', 'href' => '/favicon{theme}.png']], // {theme} is "-copper", or none
 *         'theme_colors'  => ['default' => '#FAF8F5', 'copper' => '#B87333'],
 *         'js_class'      => true,                // add a "js" class to <html> before the page renders
 *     ],
 *
 * Paths in stylesheets, scripts, fonts and inline CSS are relative to `path`.
 *
 * @api
 */
final readonly class SiteAssetsOptions
{
    /**
     * @param list<string>                $stylesheets
     * @param list<string>                $scripts
     * @param array<string, string>       $attributes
     * @param list<string>                $preloadFonts
     * @param list<string>                $inlineCss
     * @param list<array<string, string>> $icons
     * @param array<string, string>       $themeColors
     *
     * @mago-expect lint:excessive-parameter-list One parameter per setting, given by name.
     */
    public function __construct(
        public string $path = '/asset',
        public string $version = '',
        public array $stylesheets = [],
        public array $scripts = [],
        public array $attributes = [],
        public array $preloadFonts = [],
        public array $inlineCss = [],
        public array $icons = [],
        public array $themeColors = [],
        public bool $jsClass = false,
    ) {}

    /**
     * The options in a `site_assets` config block; values of the wrong type are left out.
     *
     * @param array<array-key, mixed> $config
     */
    public static function fromArray(array $config): self
    {
        return new self(
            path: ConfigValues::text($config, 'path', '/asset'),
            version: ConfigValues::text($config, 'version'),
            stylesheets: ConfigValues::strings($config, 'stylesheets'),
            scripts: ConfigValues::strings($config, 'scripts'),
            attributes: ConfigValues::map($config, 'attributes'),
            preloadFonts: ConfigValues::strings($config, 'preload_fonts'),
            inlineCss: ConfigValues::strings($config, 'inline_css'),
            icons: ConfigValues::links($config, 'icons'),
            themeColors: ConfigValues::map($config, 'theme_colors'),
            jsClass: ConfigValues::flag($config, 'js_class'),
        );
    }

    /**
     * $file's address under the asset path.
     */
    public function url(string $file): string
    {
        return rtrim($this->path, characters: '/') . '/' . ltrim($file, characters: '/');
    }

    /**
     * $file's address with the version after its first folder: css/app.css is
     * /asset/css/1.1.9/app.css. A file outside a folder, or without a version, keeps its address.
     */
    public function versioned(string $file): string
    {
        $file  = ltrim($file, characters: '/');
        $slash = strpos($file, needle: '/');
        if ('' === $this->version || false === $slash) {
            return $this->url($file);
        }

        return $this->url(substr($file, offset: 0, length: $slash) . "/{$this->version}" . substr($file, $slash));
    }
}
