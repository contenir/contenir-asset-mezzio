<?php

declare(strict_types=1);

namespace Contenir\Asset\Mezzio\Security;

use function preg_match;
use function str_contains;

/**
 * Decides whether a request-supplied, already URL-decoded path or path segment
 * may be joined onto a directory on disk or passed to a storage backend.
 *
 * Every check runs on the decoded value, before any filesystem access:
 *
 * - a `..` segment (`..`, `../x`, `x/..`, `x/../y`) is refused, so the path
 *   cannot climb out of the directory it is joined onto;
 * - a null byte is refused, so a C-level path cannot be truncated;
 * - a backslash is refused, so a Windows separator cannot smuggle a segment;
 * - a percent-encoded dot, slash, backslash or null byte left over after
 *   decoding (`%2e`, `%2f`, `%5c`, `%00`, in either case) is refused, so a
 *   double-encoded `%252e%252e` cannot become `..` in a later decode.
 *
 * @api
 */
final class PathGuard
{
    private const string PARENT_SEGMENT = '#(?:^|/)\.\.(?:/|$)#';

    private const string ENCODED_DOT_OR_SEPARATOR = '#%(?:2e|2f|5c|00)#i';

    /**
     * Whether $path, a decoded relative path, stays inside the directory it is
     * joined onto.
     */
    public static function isSafePath(string $path): bool
    {
        return (
            ! str_contains($path, "\0")
                && ! str_contains($path, '\\')
                && 1 !== preg_match(self::ENCODED_DOT_OR_SEPARATOR, $path)
                && 1 !== preg_match(self::PARENT_SEGMENT, $path)
        );
    }

    /**
     * Whether $segment, a decoded single path segment such as a filename, is
     * safe and carries no separator of its own.
     */
    public static function isSafeSegment(string $segment): bool
    {
        return ! str_contains($segment, '/') && self::isSafePath($segment);
    }
}
