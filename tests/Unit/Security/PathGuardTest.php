<?php

declare(strict_types=1);

namespace Contenir\Asset\Mezzio\Tests\Unit\Security;

use Contenir\Asset\Mezzio\Security\PathGuard;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
#[Group('security')]
final class PathGuardTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function safePathProvider(): array
    {
        return [
            'single segment'          => ['news'],
            'nested'                  => ['news/2024/june'],
            'leading and trailing /'  => ['/news/'],
            'dots inside a segment'   => ['a..b/c.d'],
            'three dots'              => ['.../x'],
            'dot segment'             => ['./news'],
            'trailing dots'           => ['news../x..'],
            'space and parentheses'   => ['My Folder/pic (1)'],
            'percent sign'            => ['100% off'],
            'other encoded character' => ['a%20b'],
            'empty'                   => [''],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unsafePathProvider(): array
    {
        return [
            'parent segment alone'       => ['..'],
            'leading parent segment'     => ['../outside'],
            'trailing parent segment'    => ['news/..'],
            'inner parent segment'       => ['news/../../outside'],
            'rooted parent segment'      => ['/../outside'],
            'null byte'                  => ["news\0"],
            'backslash'                  => ['news\\outside'],
            'backslash parent segment'   => ['..\\outside'],
            'encoded dot, lower case'    => ['%2e%2e/outside'],
            'encoded dot, upper case'    => ['%2E%2E/outside'],
            'encoded slash, lower case'  => ['..%2foutside'],
            'encoded slash, upper case'  => ['..%2Foutside'],
            'encoded backslash'          => ['..%5Coutside'],
            'encoded null byte'          => ['news%00.jpg'],
            'encoded dot inside segment' => ['a%2eb'],
        ];
    }

    #[Test]
    #[DataProvider('safePathProvider')]
    public function acceptsAPathThatStaysInside(string $path): void
    {
        static::assertTrue(PathGuard::isSafePath($path));
    }

    #[Test]
    public function acceptsASegmentWithoutASeparator(): void
    {
        static::assertTrue(PathGuard::isSafeSegment('pic one (1).jpg'));
    }

    #[Test]
    #[DataProvider('unsafePathProvider')]
    public function refusesAPathThatCouldClimbOut(string $path): void
    {
        static::assertFalse(PathGuard::isSafePath($path));
    }

    #[Test]
    public function refusesASegmentCarryingASeparator(): void
    {
        static::assertFalse(PathGuard::isSafeSegment('sub/pic.jpg'));
    }

    #[Test]
    #[DataProvider('unsafePathProvider')]
    public function refusesASegmentThatCouldClimbOut(string $segment): void
    {
        static::assertFalse(PathGuard::isSafeSegment($segment));
    }
}
