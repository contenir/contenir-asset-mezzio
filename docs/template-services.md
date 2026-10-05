# Template services

The four template services are plain invokable classes with no base class and
no template-engine dependency. The `ConfigProvider` registers each one under
`dependencies` (fetch it from the container) and under `view_helpers`.
laminas-view's `HelperPluginManager` accepts any callable, so with
`mezzio/mezzio-laminasviewrenderer` they work as helpers with nothing else to
install.

All of them return raw strings, so escape them for the output context.

| Service (helper name) | Signature | Returns |
| --- | --- | --- |
| `View\StorageSrcSet` (`storageSrcSet`) | `(?string $path, string $profile)` | `url 320w, url 640w…` over the profile's ladder, in the source format |
| `View\StorageSizes` (`storageSizes`) | `(string $profile)` | The profile's `sizes` value, or `''` when the profile is unknown |
| `View\StorageSources` (`storageSources`) | `(?string $path, string $profile, bool $lazy = false)` | One `<source type="image/…">` per profile format, with `sizes`. `$lazy` writes `data-lazysrc-srcset` instead of `srcset` |
| `View\StorageUrl` (`storageUrl`) | `(?string $path, ?string $variant = null, ?string $format = null)` | The original URL, or the URL of one variant (optionally in another format) |

A null or empty `$path` renders `''`. An unknown profile (srcset/sources) or
variant (url) logs a warning to the container's `Psr\Log\LoggerInterface`, if
one is registered. `storageUrl()` still returns the URL it would have built.
The laminas-mvc adapter raised an `E_USER_WARNING` here. Mezzio's error
handler would turn that warning into a 500 for the whole page, so this
package logs instead.

## laminas-view

```php
<picture>
    <?= $this->storageSources($asset->path, 'card', true) ?>
    <img data-lazysrc-srcset="<?= $this->escapeHtmlAttr($this->storageSrcSet($asset->path, 'card')) ?>"
         sizes="<?= $this->storageSizes('card') ?>"
         src="<?= $this->escapeHtmlAttr($this->storageUrl($asset->path, 'card-320')) ?>"
         alt="">
</picture>
```

## Plates

```php
$engine->registerFunction('storageSrcSet', $container->get(StorageSrcSet::class));
$engine->registerFunction('storageSizes', $container->get(StorageSizes::class));
```

## Twig

```php
$twig->addFunction(new TwigFunction('storage_srcset', $container->get(StorageSrcSet::class)));
$twig->addFunction(new TwigFunction('storage_sources', $container->get(StorageSources::class), ['is_safe' => ['html']]));
```

## AssetUrlBuilder

`Service\AssetUrlBuilder` is the pure URL builder behind them:

```php
$urls = new AssetUrlBuilder('/media');                 // local scheme
$urls->originalUrl('/media/news/a b.jpg');             // "/media/news/a%20b.jpg"
$urls->variantUrl('news/a.jpg', 'card-320', 'webp');   // "/media/news/_variant/card-320/a.webp"

$cdn = new AssetUrlBuilder('https://cdn.test', 's3');  // sibling scheme
$cdn->variantUrl('news/a.jpg', 'card-320', 'avif');    // "https://cdn.test/news/a__card-320.avif"
$cdn->srcset('news/a.jpg', $profile->variants);
```

Path segments are percent-encoded, so a filename with spaces still gives a
parseable srcset. A local path that already starts with the public prefix
does not get the prefix twice.
