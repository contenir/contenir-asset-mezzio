# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project
adheres to [Semantic Versioning](https://semver.org/).

## [2.0.0] - Unreleased

First release. This package brings `contenir/contenir-asset-laminas-mvc` 2.2
to Mezzio. See "Coming from contenir-asset-laminas-mvc" in the README.

### Added

- `Handler\AssetVariantHandler`, a PSR-15 handler that serves
  `/asset/<folder>/_variant/<name>/<filename>`. It generates a missing local
  variant on demand and returns it as a PSR-7 stream with `Content-Type`,
  `Content-Length` and long-lived `Cache-Control`, or an empty 404.
- `Handler\AssetVariantGenerateHandler`, the secret-guarded origin endpoint
  for the S3/R2 edge miss-proxy. Its responses are JSON with
  `Cache-Control: no-store`.
- `Security\PathGuard`. It refuses `..` segments, null bytes, backslashes and
  encoded dots or separators left after decoding in the folder, variant name,
  filename and generate `key`, before any filesystem access.
- `Service\ProfileProviderService`, `VariantGenerator` (typed against
  `ImageResizerInterface`), `OnDemandVariantResolver` and `AssetUrlBuilder`,
  ported unchanged apart from the guard.
- Template services `View\StorageSrcSet`, `StorageSizes`, `StorageSources` and
  `StorageUrl`. They are plain invokables that work with any template engine,
  and are registered as laminas-view helpers. Unknown profiles and variants
  log a PSR-3 warning.
- The `storage:variants` command, registered for laminas-cli.
- CI on PHP 8.3, 8.4 and 8.5 with Codecov, ImageMagick and Infection at 100%
  MSI.
