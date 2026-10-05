# Serving and generating variants

## Local: `AssetVariantHandler`

```php
$app->get('/asset/{path:.+}', AssetVariantHandler::class, 'asset.variant');
```

The web server serves existing variant files directly, so only a miss reaches
PHP. The handler matches the request path against
`/asset/<folder>/_variant/<name>/<filename>` (where `<name>` is `[A-Za-z0-9_-]+`
and `<filename>` has no `/`). It decodes the folder and filename exactly once
and asks `VariantGenerator` for the file. The response is one of:

- 200 with the file as a stream body, plus `Content-Type` (from the extension:
  avif, gif, jpeg/jpg, png, webp, otherwise `application/octet-stream`),
  `Content-Length` and `Cache-Control: public, max-age=31536000`;
- an empty 404 for any other path, or when the variant cannot be produced.

The handler never calls `header()`, `readfile()` or `exit`. The application's
emitter sends the response.

`VariantGenerator::generate($folder, $name, $filename)`:

1. refuses an unsafe folder, name or filename (null), see below;
2. looks the variant up by name (unknown: null);
3. finds the original in `<root>/asset/<folder>/` by basename, trying the
   requested name first, then jpg, jpeg, png, gif, webp and avif in either case;
4. writes `<root>/asset/<folder>/_variant/<name>/<base>.<requested ext>`. If
   the requested format cannot be encoded (for example there is no AVIF
   delegate), it writes the source format instead, and it reuses either file
   if it already exists.

### Path traversal guard

`Security\PathGuard` checks the decoded values before any filesystem access:

| Refused | Example request path |
| --- | --- |
| A `..` segment | `/asset/%2e%2e/_variant/card/pic.png` |
| A null byte | `/asset/news%00/_variant/card/pic.png` |
| A backslash | `/asset/..%5c/_variant/card/pic.png` |
| An encoded dot, slash, backslash or null byte left after decoding | `/asset/%252e%252e/_variant/card/pic.png` |
| A separator in the name or filename | `/asset/news/_variant/card/..%2F..%2Fpic.png` |

All of these get an empty 404, and nothing is read or written.

## S3/R2: `AssetVariantGenerateHandler`

```php
$app->route('/asset-variant/generate', AssetVariantGenerateHandler::class, ['GET', 'POST'], 'asset.variant.generate');
```

The edge worker calls `…/asset-variant/generate?key=<sibling key>` on a cache
miss, sending an `X-Asset-Generate-Secret` header that must equal the primary
backend's `generate_secret`.

| Response | When |
| --- | --- |
| 503 `{"error":"generation endpoint not configured"}` | No secret configured |
| 403 | Missing or wrong secret (compared with `hash_equals()`) |
| 400 `{"error":"missing key"}` | No `key`, or not a string |
| 404 | The key fails `PathGuard`, or no backend could generate it |
| 200 `{"url":"…"}` | Generated (or already present) |

Every response is sent with `Cache-Control: no-store`. A `WriteException`
from the backend propagates to the application's error handler.

`OnDemandVariantResolver` tries each registered backend that implements
`OnDemandVariantGeneratorInterface` in turn, and the first non-null URL wins.
