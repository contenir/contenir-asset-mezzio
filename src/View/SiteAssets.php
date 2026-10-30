<?php

declare(strict_types=1);

namespace Contenir\Asset\Mezzio\View;

use function array_map;
use function implode;
use function str_replace;

/**
 * The site's own stylesheets, scripts, fonts and icons, as markup for any
 * template engine. In the layout:
 *
 *     <head>
 *         <?= $this->siteAssets()->head($theme, $nonce) ?>
 *     </head>
 *     <body>
 *         ...
 *         <?= $this->siteAssets()->bodyEnd($nonce) ?>
 *     </body>
 *
 * Stylesheets and scripts take the version in their path, so a new version
 * is fetched afresh: /asset/css/1.1.9/app.css. The web server maps that path
 * back to the file. Fonts are preloaded at the address the stylesheet asks
 * for, without the version.
 *
 * @api
 */
final readonly class SiteAssets
{
    /**
     * @param string $publicDirectory The web root, for the inline CSS files
     */
    public function __construct(
        private SiteAssetsOptions $options,
        private string $publicDirectory,
    ) {}

    /**
     * What goes at the end of <body>: the scripts.
     *
     * @param ?string $nonce A CSP nonce for the scripts
     */
    public function bodyEnd(?string $nonce = null): string
    {
        return implode("\n", array_map(
            fn(string $script): string => AssetMarkup::element(
                'script',
                [
                    'src' => $this->options->versioned($script),
                    ...$this->options->attributes,
                    ...AssetMarkup::nonce($nonce),
                ],
                '',
            ),
            $this->options->scripts,
        ));
    }

    /**
     * What goes in <head>: the stylesheets, the font and script preloads,
     * the icons and theme colour for $theme, the inline CSS, and the script
     * that marks <html> as having JavaScript.
     *
     * @param ?string $theme The page's theme, for its icons and colour; none is "default"
     * @param ?string $nonce A CSP nonce for the inline style and script
     */
    public function head(?string $theme = null, ?string $nonce = null): string
    {
        $theme = null === $theme || '' === $theme ? 'default' : $theme;

        return implode("\n", [
            ...$this->links(),
            ...$this->icons($theme),
            ...$this->themeColour($theme),
            ...$this->inline($nonce),
        ]);
    }

    /**
     * The icons, with "{theme}" in their address as "-copper" for that theme, or nothing for the default.
     *
     * @return list<string>
     */
    private function icons(string $theme): array
    {
        $suffix = 'default' === $theme ? '' : "-{$theme}";

        return array_map(
            /** @param array<string, string> $icon */
            static fn(array $icon): string => AssetMarkup::element('link', [
                ...$icon,
                'href' => str_replace('{theme}', $suffix, $icon['href'] ?? ''),
            ]),
            $this->options->icons,
        );
    }

    /**
     * The inline CSS and the script marking <html> as having JavaScript.
     *
     * @return list<string>
     */
    private function inline(?string $nonce): array
    {
        $css = InlineCss::read($this->publicDirectory, array_map($this->options->url(...), $this->options->inlineCss));

        return [
            ...('' === $css ? [] : [AssetMarkup::element('style', AssetMarkup::nonce($nonce), "\n{$css}")]),
            ...(
                $this->options->jsClass
                    ? [AssetMarkup::element(
                        'script',
                        AssetMarkup::nonce($nonce),
                        'document.documentElement.classList.add("js")',
                    )]
                    : []
            ),
        ];
    }

    /**
     * @return list<string>
     */
    private function links(): array
    {
        $options = $this->options;

        return [
            ...array_map(
                static fn(string $file): string => AssetMarkup::element('link', [
                    'rel'  => 'stylesheet',
                    'href' => $options->versioned($file),
                    ...$options->attributes,
                ]),
                $options->stylesheets,
            ),
            ...array_map(
                static fn(string $font): string => AssetMarkup::element('link', [
                    'rel'         => 'preload',
                    'href'        => $options->url($font),
                    'as'          => 'font',
                    'type'        => 'font/woff2',
                    'crossorigin' => 'anonymous',
                ]),
                $options->preloadFonts,
            ),
            ...array_map(
                static fn(string $script): string => AssetMarkup::element('link', [
                    'rel'  => 'preload',
                    'href' => $options->versioned($script),
                    'as'   => 'script',
                ]),
                $options->scripts,
            ),
        ];
    }

    /**
     * @return list<string>
     */
    private function themeColour(string $theme): array
    {
        $colour = $this->options->themeColors[$theme] ?? $this->options->themeColors['default'] ?? null;

        return null === $colour ? [] : [AssetMarkup::element('meta', ['name' => 'theme-color', 'content' => $colour])];
    }

    public function __invoke(): self
    {
        return $this;
    }
}
