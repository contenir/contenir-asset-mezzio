<?php

declare(strict_types=1);

namespace Contenir\Asset\Mezzio\Tests\TestAsset\Storage;

use Contenir\Storage\Entry;
use Contenir\Storage\Exception\NotFoundException;
use Contenir\Storage\ImageMeta;
use Contenir\Storage\ListOptions;
use Contenir\Storage\MissingVariantsReporterInterface;
use Contenir\Storage\OnDemandVariantGeneratorInterface;
use Contenir\Storage\StorageInterface;
use Contenir\Storage\UploadInput;
use DateTimeImmutable;
use LogicException;

use function array_key_exists;
use function basename;
use function in_array;

/**
 * In-memory storage double exercising only what {@see \Contenir\Asset\Mezzio\Command\VariantsCommand}
 * touches: a flat list() of originals, plus the missing/regenerate pair.
 *
 * Which variants an original is entitled to is the backend's business, so the
 * double is told directly rather than deriving it — the command under test is
 * responsible only for walking originals and tallying what it is handed.
 * The remaining StorageInterface methods are not exercised and throw.
 */
final class FakeOnDemandStorage implements
    StorageInterface,
    MissingVariantsReporterInterface,
    OnDemandVariantGeneratorInterface
{
    /** @var array<string, true> */
    private array $existing = [];

    /** @var list<string> Keys passed to generateForKey, in order. */
    public array $generated = [];

    /**
     * @param list<string>                $originals Original image keys to enumerate.
     * @param array<string, list<string>> $outstanding Original key => variant keys it still lacks.
     * @param list<string>                $unreadable Original keys that raise NotFoundException.
     */
    public function __construct(
        private array $originals,
        private array $outstanding = [],
        private array $unreadable = [],
    ) {
        foreach ($originals as $key) {
            $this->existing[$key] = true;
        }
    }

    public function delete(string $path): void
    {
        throw new LogicException('not exercised');
    }

    public function exists(string $path): bool
    {
        return array_key_exists($path, $this->existing);
    }

    public function generateForKey(string $variantKey): ?string
    {
        $this->generated[]           = $variantKey;
        $this->existing[$variantKey] = true;

        return "https://cdn.test/{$variantKey}";
    }

    public function imageMeta(string $path): ImageMeta
    {
        throw new LogicException('not exercised');
    }

    public function list(string $path, ?ListOptions $options = null): iterable
    {
        if ('' !== $path) {
            return;
        }
        foreach ($this->originals as $key) {
            yield new Entry($key, basename($key), $key, false, 1, new DateTimeImmutable('@0'), 'image/jpeg');
        }
    }

    public function missingVariants(string $path): array
    {
        if (in_array($path, $this->unreadable, strict: true)) {
            throw NotFoundException::forPath($path);
        }

        return $this->outstanding[$path] ?? [];
    }

    public function regenerateMissingVariants(string $path): array
    {
        $keys = $this->missingVariants($path);
        foreach ($keys as $key) {
            $this->generated[]    = $key;
            $this->existing[$key] = true;
        }
        $this->outstanding[$path] = [];

        return $keys;
    }

    public function rename(string $from, string $to): void
    {
        throw new LogicException('not exercised');
    }

    public function store(UploadInput $upload, string $directory): Entry
    {
        throw new LogicException('not exercised');
    }

    public function thumbnailUrl(string $path): ?string
    {
        throw new LogicException('not exercised');
    }

    public function url(string $path, ?string $variant = null): ?string
    {
        throw new LogicException('not exercised');
    }

    /** @return array<string, string> */
    public function urlsForKey(string $path): array
    {
        throw new LogicException('not exercised');
    }

    /** @return array<string, string> */
    public function variantUrls(string $path, string $variantName): array
    {
        throw new LogicException('not exercised');
    }
}
