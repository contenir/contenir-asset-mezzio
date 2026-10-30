<?php

declare(strict_types=1);

namespace Contenir\Asset\Mezzio\View;

use function file_get_contents;
use function is_file;
use function rtrim;
use function trim;

/**
 * Reads the CSS a page inlines from the web root: each file that exists and
 * isn't blank, one after another.
 *
 * @internal
 */
final class InlineCss
{
    /**
     * @param string       $publicDirectory The web root
     * @param list<string> $addresses       The files' addresses under the web root
     */
    public static function read(string $publicDirectory, array $addresses): string
    {
        $css = '';
        foreach ($addresses as $address) {
            $path     = $publicDirectory . $address;
            $contents = is_file($path) ? file_get_contents($path) : false;
            if (false === $contents || '' === trim($contents)) {
                continue;
            }

            $css .= rtrim($contents) . "\n";
        }

        return $css;
    }
}
