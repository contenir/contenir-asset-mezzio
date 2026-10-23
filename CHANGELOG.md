# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project
adheres to [Semantic Versioning](https://semver.org/).

## [2.1.0] - Unreleased

### Added

- `ImageResizeHandler`, serving contenir-asset's deprecated `.WxH` scheme
  (`/asset/<folder>/.480x/<file>`, as its `assetSrcSet()` emits) through
  its `LegacyResizer`, so a Mezzio site gets the same legacy resize route
  the laminas-mvc adapter has. Route it in `config/routes.php` like the
  variant handler.
- `Service\AnimatedSourceCheck` tells whether a stored image is animated, by
  reading the file's header under the local backend's `root_path` with
  contenir-storage's `AnimationDetector`. Only `.gif`, `.png` and `.webp`
  files are read, and each answer is kept for the request. Sources on an
  object store are reported as still for now.

### Changed

- Requires `contenir/contenir-asset` ^2.0@RC (RC2 or later), which no
  longer depends on laminas-mvc.
- `storageSrcSet()` and `storageSources()` render `''` for an animated
  source, so the `<img>`'s `src`, the original, is shown at every size
  rather than a resized still of one frame. Both take an optional
  `AnimatedSourceCheck` as a last constructor argument, which their
  factories supply.
- `VariantGenerator`, and so `AssetVariantHandler`, answers a variant request
  for an animated source with the original file instead of resizing it.
- Requires `contenir/contenir-storage` 2.4, for `AnimationDetector`.

## [2.0.0] - 2026-10-05

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
