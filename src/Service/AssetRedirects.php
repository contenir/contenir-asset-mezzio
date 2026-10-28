<?php

declare(strict_types=1);

namespace Contenir\Asset\Mezzio\Service;

use JsonException;

use function dirname;
use function file_get_contents;
use function is_array;
use function is_file;
use function is_string;
use function json_decode;
use function ltrim;
use function pathinfo;

use const JSON_THROW_ON_ERROR;
use const PATHINFO_FILENAME;

/**
 * Where a stored file the CMS moved or renamed has gone, read from the JSON file the CMS keeps of
 * old paths and new ones (relative to the storage root, without a leading slash), so an old
 * address can be answered with a redirect rather than a 404. A variant of a moved file is found
 * by its folder and name, as its extension is the variant's format.
 *
 * @api
 */
final class AssetRedirects
{
    /** @var array<string, string>|null Loaded on first use. */
    private ?array $redirects = null;

    public function __construct(
        private readonly string $file,
    ) {}

    /**
     * Where a file now is, or null when it hasn't moved.
     */
    public function of(string $path): ?string
    {
        return $this->all()[ltrim($path, characters: '/')] ?? null;
    }

    /**
     * Where the file a variant was made of now is, found by its folder and name, or null.
     */
    public function ofVariant(string $folder, string $filename): ?string
    {
        $folder = ltrim($folder, characters: '/');
        $name   = pathinfo($filename, PATHINFO_FILENAME);
        foreach ($this->all() as $from => $to) {
            if (dirname($from) === $folder && pathinfo($from, PATHINFO_FILENAME) === $name) {
                return $to;
            }
        }

        return null;
    }

    /**
     * @return array<string, string>
     *
     * @mago-expect analysis:mixed-assignment Stored JSON is untyped; only text pairs are kept.
     * @mago-expect lint:halstead Reading the file once, checking it at each step.
     */
    private function all(): array
    {
        if (null !== $this->redirects) {
            return $this->redirects;
        }

        $this->redirects = [];
        if (! is_file($this->file)) {
            return $this->redirects;
        }

        try {
            /** @var mixed $stored */
            $stored = json_decode(
                (string) file_get_contents($this->file),
                associative: true,
                flags: JSON_THROW_ON_ERROR,
            );
        } catch (JsonException) {
            return $this->redirects;
        }

        foreach (is_array($stored) ? $stored : [] as $from => $to) {
            if (! is_string($from) || ! is_string($to)) {
                continue;
            }

            $this->redirects[$from] = $to;
        }

        return $this->redirects;
    }
}
