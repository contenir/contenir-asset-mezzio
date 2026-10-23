<?php

declare(strict_types=1);

namespace Contenir\Asset\Mezzio\Service;

use Contenir\Asset\Mezzio\Security\PathGuard;
use Contenir\Storage\Image\AnimationDetector;

use function in_array;
use function pathinfo;
use function rawurldecode;
use function strtolower;

use const PATHINFO_EXTENSION;

/**
 * Whether a stored image is animated, so the template helpers can serve it as
 * itself instead of offering resized variants that would keep one frame.
 *
 * Only the local backend is inspected, where the file sits under the web root
 * at its original URL and the check is a short read of its header. An object
 * store would need a remote read per image, so its sources are reported as
 * still for now. Only extensions whose formats can animate are read at all,
 * and each answer is kept for the rest of the request.
 *
 * @api
 */
final class AnimatedSourceCheck
{
    /** @var list<string> */
    private const array ANIMATABLE_EXTENSIONS = ['gif', 'png', 'webp'];

    /** @var array<string, bool> Original URL => whether its file is animated. */
    private array $answers = [];

    public function __construct(
        private readonly AssetUrlBuilder $urls,
        private readonly string $rootPath,
        private readonly bool $local = true,
    ) {}

    public function isAnimated(string $path): bool
    {
        if (! $this->local || '' === $path) {
            return false;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (! in_array($extension, self::ANIMATABLE_EXTENSIONS, strict: true)) {
            return false;
        }

        $url = rawurldecode($this->urls->originalUrl($path));
        if (! PathGuard::isSafePath($url)) {
            return false;
        }

        return $this->answers[$url] ??= AnimationDetector::isAnimatedFile($this->rootPath . $url);
    }
}
