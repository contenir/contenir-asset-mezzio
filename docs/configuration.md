# Configuration

The package reads only the `storage` block, through `contenir/contenir-storage`'s
`StorageConfig::primaryBackendConfig()`:

| Key on the primary backend | Used by | Default |
| --- | --- | --- |
| `type` | URL scheme: `local` gives `_variant/<name>/<file>`, anything else sibling keys | `local` |
| `public_path` | URL prefix for a local backend | `''` |
| `publicUrl` / `public_base_url` | CDN base for an S3/R2 backend (`public_base_url` wins) | `''` |
| `root_path` | Web root holding `asset/…` originals for on-demand generation | `public` |
| `binary` | ImageMagick CLI path; auto-discovered when unset | — |
| `generate_secret` | Shared secret for the generate endpoint; empty disables it | `''` |

Empty strings count as unset. A relative `root_path` resolves against the
working directory, which for a Mezzio site is the project root.

`storage.variants` feeds `ProfileProviderService`:

- a `dimensions` ladder becomes a responsive profile whose variants are
  `<name>-<width>`; `sizes` and `formats` are read from the same entry;
  `'role' => 'preview'` registers the variants but no profile;
- a legacy `variants` map (`'tile' => ['variants' => ['tile-320' => […]], 'sizes' => …]`)
  is still accepted; `admin-thumb` inside it is never part of the srcset;
- a flat variant (`width`/`height`) is registered for lookup only.

Variant names must be unique across profiles: the request URL carries only
the name.

## Container services the application provides

| Service | Needed by |
| --- | --- |
| `Psr\Http\Message\ResponseFactoryInterface`, `Psr\Http\Message\StreamFactoryInterface` | Both handlers (laminas-diactoros' ConfigProvider registers them) |
| `Contenir\Storage\StorageManager` | `storage:variants` and the generate endpoint, for example a factory calling `StorageConfig::fromArray()` |
| `Psr\Log\LoggerInterface` (optional) | The template services' warnings about unknown profiles and variants |

## Overriding the resizer

`Contenir\Storage\Image\ImageResizerInterface` is an alias of the shipped
`ImageResizer`. Point it at your own service to change how variants are
encoded:

```php
'dependencies' => [
    'aliases'   => [ImageResizerInterface::class => MyResizer::class],
    'factories' => [MyResizer::class => MyResizerFactory::class],
],
```
