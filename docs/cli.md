# storage:variants

```bash
vendor/bin/laminas storage:variants [--backend=r2] [--prefix=asset/news] [--limit=500] [--generate] [-v]
```

The `ConfigProvider` registers the command under the `laminas-cli` key, so it
is available once `laminas/laminas-cli` is installed. It is also an ordinary
`symfony/console` command, resolved from the container as
`Contenir\Asset\Mezzio\Command\VariantsCommand`, so you can add it to any
console application.

It walks the backend recursively from `--prefix`. For every image original
(keys containing `__` are variant siblings and are skipped):

- without `--generate`, it reports the variant keys the original is missing,
  through `MissingVariantsReporterInterface` (the backend must implement it);
- with `--generate`, it creates them through `regenerateMissingVariants()`.

It prints a table of originals, complete originals, missing or generated keys
and errors. With `-v` it also lists every key and prints a progress line every
100 originals. An original deleted during the run is skipped and not counted
as an error. The exit code is 1 when any original failed, or when the backend
is unknown or cannot report.

`--backend` defaults to the primary backend. The variants each original
should have come from `storage.variants` and `storage.paths`, which the
backend applies. The command needs the application's
`Contenir\Storage\StorageManager` service.
