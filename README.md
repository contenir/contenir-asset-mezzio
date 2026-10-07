# contenir/contenir-asset-mezzio

[![Continuous Integration](https://github.com/contenir/contenir-asset-mezzio/actions/workflows/continuous-integration.yml/badge.svg)](https://github.com/contenir/contenir-asset-mezzio/actions/workflows/continuous-integration.yml)
[![codecov](https://codecov.io/gh/contenir/contenir-asset-mezzio/graph/badge.svg)](https://codecov.io/gh/contenir/contenir-asset-mezzio)

Mezzio adapter for Contenir assets: keyed, profile-driven responsive image
variants (including WebP and AVIF) on top of
[contenir/contenir-storage](https://github.com/contenir/contenir-storage).
Use it on a Mezzio site that does not run the full CMS.

A template names one profile (for example `'card'`) and gets the whole
responsive set: srcset ladder, `sizes` attribute and `<picture>` sources in
extra formats. The profiles are the same `storage.variants` declarations the
CMS and `contenir/contenir-storage` read, so there is one source of truth.

- **Template services:** `StorageSrcSet`, `StorageSizes`, `StorageSources` and
  `StorageUrl`. They are plain invokable services, so any template engine can
  call them, and laminas-view picks them up as `storageSrcSet()` and so on.
- **On-demand variants:** two PSR-15 handlers. `AssetVariantHandler` generates
  a missing local variant on its first request and returns it. `AssetVariantGenerateHandler`
  is a secret-guarded endpoint an edge worker calls to generate a missing
  S3/R2 sibling.
- **CLI:** `storage:variants` audits a backend and can backfill it.

## Requirements

- PHP 8.3, 8.4 or 8.5
- `contenir/contenir-storage` 2.2+
- A PSR-7/PSR-17 implementation, with its factories registered in the container as
  `Psr\Http\Message\ResponseFactoryInterface` and `Psr\Http\Message\StreamFactoryInterface`
  (`laminas/laminas-diactoros`'s ConfigProvider does this)
- `symfony/console` 6.4.10+ or 7.1.3+ for the command. Install `laminas/laminas-cli`
  to run it as `vendor/bin/laminas storage:variants`
- ImageMagick (the imagick extension or the `magick`/`convert` CLI) for local generation
- Optional: `mezzio/mezzio-laminasviewrenderer` to call the template services as laminas-view helpers

No class in `src/` depends on a particular PSR-7 implementation, router,
template engine or laminas-mvc.

## Installation

```bash
composer require contenir/contenir-asset-mezzio
```

With `laminas/laminas-component-installer`, the `Contenir\Asset\Mezzio\ConfigProvider` is
added to `config/config.php` for you. If you don't use it, add the provider yourself:

```php
$aggregator = new ConfigAggregator([
    // ...
    \Contenir\Asset\Mezzio\ConfigProvider::class,
]);
```

## Configuration

Everything goes under the `storage` key that `contenir/contenir-storage` reads:

```php
// config/autoload/storage.global.php
return [
    'storage' => [
        'backend' => [
            'local' => ['type' => 'local', 'root_path' => 'public', 'public_path' => '/asset'],
            // or an S3/R2 primary:
            // 'r2' => ['type' => 's3', 'default' => true, 'publicUrl' => 'https://cdn…', 'generate_secret' => '…', …],
        ],
        'variants' => [
            'admin-thumb' => ['width' => 180, 'height' => 180, 'fit' => 'contain'],
            'card'        => [
                'dimensions' => ['320x', '640x', '960x'],
                'sizes'      => '(min-width: 768px) 33vw, 100vw',
                'formats'    => ['avif', 'webp'],
            ],
        ],
    ],
];
```

The primary backend sets the URL scheme: `_variant/<name>/` for a local
backend, `<key>__<name>.<ext>` siblings on S3/R2. The command and the generate
endpoint also need a `Contenir\Storage\StorageManager` service, which the
application registers. See [docs/configuration.md](docs/configuration.md).

## Routing

Routes are not registered automatically, so the application decides which
paths are public. Add them in `config/routes.php`:

```php
use Contenir\Asset\Mezzio\Handler\AssetVariantGenerateHandler;
use Contenir\Asset\Mezzio\Handler\AssetVariantHandler;

// Local backends: a variant missing from public/asset/…/_variant/… is generated on first request.
$app->get('/asset/{path:.+}', AssetVariantHandler::class, 'asset.variant');

// S3/R2 backends: the edge worker's miss-proxy.
$app->route('/asset-variant/generate', AssetVariantGenerateHandler::class, ['GET', 'POST'], 'asset.variant.generate');
```

`AssetVariantHandler` reads the folder, name and filename from the request
path itself (`/asset/<folder>/_variant/<name>/<filename>`). It ignores route
attributes, so any router and pattern that sends those paths to it works, and
any other path gets a 404.

## Template services

| Service (laminas-view helper) | Returns |
| --- | --- |
| `View\StorageSrcSet` (`storageSrcSet`) | `url 320w, url 640w…` over the profile's ladder, in the source format |
| `View\StorageSizes` (`storageSizes`) | The profile's `sizes` value, or `''` |
| `View\StorageSources` (`storageSources`) | One `<source type="image/…">` per profile format, with `sizes`. `$lazy` writes `data-lazysrc-srcset` instead of `srcset` |
| `View\StorageUrl` (`storageUrl`) | The original URL, or the URL of one variant (optionally in another format) |

```php
<picture>
    <?= $this->storageSources($asset->path, 'card') ?>
    <img srcset="<?= $this->escapeHtmlAttr($this->storageSrcSet($asset->path, 'card')) ?>"
         sizes="<?= $this->storageSizes('card') ?>"
         src="<?= $this->escapeHtmlAttr($this->storageUrl($asset->path, 'card-320')) ?>"
         alt="">
</picture>
```

With Twig, Plates or another engine, fetch the services from the container and
register them as functions. See [docs/template-services.md](docs/template-services.md).

## Handlers and services

| Class | Role |
| --- | --- |
| `Handler\AssetVariantHandler` | Serves `/asset/<folder>/_variant/<name>/<filename>`, generating a missing variant first |
| `Handler\AssetVariantGenerateHandler` | Generates an S3/R2 sibling for an edge worker, guarded by `X-Asset-Generate-Secret` |
| `Service\ProfileProviderService` | Typed `Profile`/`Variant` views of `storage.variants` |
| `Service\VariantGenerator` | Finds the original on disk and writes the variant with an `ImageResizerInterface` |
| `Service\OnDemandVariantResolver` | Hands a sibling key to the backend that can generate it |
| `Service\AssetUrlBuilder` | Builds original and variant URLs, percent-encoded, for both schemes |
| `Security\PathGuard` | Refuses traversal in decoded request paths (see [Security](#security)) |
| `Command\VariantsCommand` | `storage:variants` |

Every class is `final`. To swap the resizer, register your own
`Contenir\Storage\Image\ImageResizerInterface` service, which overrides the
default alias to `ImageResizer`. See
[docs/variant-serving.md](docs/variant-serving.md) and [docs/cli.md](docs/cli.md).

## Security

- **Path traversal.** The folder, variant name and filename from the request
  are percent-decoded once and then checked by `Security\PathGuard` before
  anything touches the filesystem. The request gets an empty 404, and nothing
  is read or written, when a value contains:
  - a `..` segment;
  - a null byte or a backslash;
  - a percent-encoded dot, slash, backslash or null byte that is still there
    after decoding (`%252e%252e` decodes to `%2e%2e`, which is refused);
  - a separator in the name or the filename.

  The generate endpoint applies the same check to `key` before any backend
  sees it.
- **Generation secret.** `AssetVariantGenerateHandler` answers 503 until the
  primary backend has a `generate_secret`. The header is compared with
  `hash_equals()`. All its responses are sent with `Cache-Control: no-store`.

## Coming from contenir-asset-laminas-mvc

| laminas-mvc 2.2 | Mezzio 2.0 |
| --- | --- |
| `Module` + `ConfigProvider` (`router`, `controllers`, `service_manager`, `view_helpers`, `laminas-cli`) | `ConfigProvider` only: `dependencies`, `view_helpers`, `laminas-cli` |
| `assetvariant` Regex route → `Controller\AssetVariantController::indexAction()` | `Handler\AssetVariantHandler`, routed by the app (`$app->get('/asset/{path:.+}', …)`) |
| `assetvariant-generate` Literal route → `Controller\AssetVariantGenerateController::generateAction()` | `Handler\AssetVariantGenerateHandler`, routed by the app |
| `Controller\Factory\*` | `Handler\Factory\*` (also needs the PSR-17 response and stream factories) |
| `View\Helper\StorageUrl`, `StorageSrcSet`, `StorageSources`, `StorageSizes` (extend `AbstractHelper`) | `View\StorageUrl`, `StorageSrcSet`, `StorageSources`, `StorageSizes`: plain invokables, same arguments and output, registered under the same helper names |
| Unknown profile or variant: `E_USER_WARNING` | A PSR-3 warning to the container's `Psr\Log\LoggerInterface`, if there is one. Mezzio's error handler would turn the warning into a 500 |
| `Service\*`, `Profile\Profile`, `Command\VariantsCommand`, `Container\Services` | The same classes and behaviour under `Contenir\Asset\Mezzio\…` |
| `VariantGenerator` `..`/null-byte folder guard | `Security\PathGuard`, extended to backslashes, encoded leftovers, and the name, filename and generate `key` |
| `Content-Type` by `mime_content_type()` | `Content-Type` by the served file's extension (no `ext-fileinfo`) |
| `Cache-Control: max-age=31536000, public` | `Cache-Control: public, max-age=31536000` |
| Generate endpoint: 400 for a non-HTTP request | Not applicable: a PSR-15 handler only receives HTTP requests |
| `storage.asset` defaults (`root_path`, `public_path`), unused since 0.5 | Dropped. The factories read the primary backend, as before |
| Config keys `storage.backend.*` (`type`, `root_path`, `public_path`, `publicUrl`, `public_base_url`, `binary`, `generate_secret`) and `storage.variants` | Unchanged |

Steps:

1. Replace `contenir/contenir-asset-laminas-mvc` with `contenir/contenir-asset-mezzio`.
2. Change `Contenir\Asset\Laminas\Mvc\` imports to `Contenir\Asset\Mezzio\`
   (`View\Helper\X` is now `View\X`, and `Controller\` is now `Handler\`).
3. Add the two routes above to `config/routes.php`.
4. Keep `config/autoload/storage.global.php` as it is.

## Documentation

- [Configuration](docs/configuration.md)
- [Template services](docs/template-services.md)
- [Serving and generating variants](docs/variant-serving.md)
- [The storage:variants command](docs/cli.md)

## Development

The QA toolchain is [contenir/contenir-qa-tools](https://github.com/contenir/contenir-qa-tools).
[Mago](https://mago.carthage.software/) is a standalone binary, installed
separately (`brew install mago`).

```bash
composer check             # everything below
composer cs-check          # mago format --check && mago lint
composer static-analysis   # mago analyze
composer test              # unit suite: no I/O, collaborators doubled
composer test-integration  # integration suite: real files, ImageMagick, a real Mezzio pipeline
composer test-coverage     # both suites, clover.xml for Codecov
composer mutation-test     # Infection over both suites (needs Xdebug or PCOV)
```

On a case-insensitive file system (the macOS default), the uppercase-extension
test is skipped, and two `VariantGenerator` case-folding mutants cannot be
killed. CI runs on Linux, which kills them.

## License

MIT. See [LICENSE](LICENSE).
