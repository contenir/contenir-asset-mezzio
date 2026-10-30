<?php

declare(strict_types=1);

namespace Contenir\Asset\Mezzio\Tests\Unit\Service;

use Contenir\Asset\Mezzio\Service\UnresizedSource;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class UnresizedSourceTest extends TestCase
{
    /**
     * @return array<string, array{string, bool}>
     */
    public static function sourceProvider(): array
    {
        return [
            'a vector'                        => ['/asset/foo/logo.svg', true],
            'a vector with an uppercase name' => ['/asset/foo/logo.SVG', true],
            'a still image'                   => ['/asset/foo/photo.jpg', false],
            'a name that only ends in svg'    => ['/asset/foo/notansvg', false],
        ];
    }

    #[Test]
    #[DataProvider('sourceProvider')]
    public function aVectorIsServedAsItIs(string $source, bool $unresized): void
    {
        static::assertSame($unresized, UnresizedSource::is($source));
    }
}
