<?php

declare(strict_types=1);

namespace Contenir\Asset\Mezzio\Tests\Unit\Service;

use Contenir\Asset\Mezzio\Service\AssetRedirects;
use Contenir\Asset\Mezzio\Service\Factory\AssetRedirectsFactory;
use Laminas\ServiceManager\ServiceManager;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function file_put_contents;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

/**
 * Where the files the CMS moved have gone, as the JSON it keeps says.
 */
#[Group('unit')]
final class AssetRedirectsTest extends TestCase
{
    private string $file;

    #[Test]
    public function findsNothingWithoutAFileOrFromABrokenOne(): void
    {
        file_put_contents($this->file, data: '{');

        static::assertSame(
            [null, null],
            [(new AssetRedirects($this->file))->of('a.png'), (new AssetRedirects('/no/such/file.json'))->of('a.png')],
        );
    }

    #[Test]
    public function findsWhereAFileOrAVariantsFileWent(): void
    {
        file_put_contents($this->file, data: '{"library/pic.jpg":"library/hans/pic.jpg","library/x.png":3}');
        $redirects = new AssetRedirects($this->file);

        static::assertSame(
            ['library/hans/pic.jpg', 'library/hans/pic.jpg', 'library/hans/pic.jpg', null, null, null],
            [
                $redirects->of('library/pic.jpg'),
                $redirects->of('/library/pic.jpg'),
                $redirects->ofVariant('/library', 'pic.webp'),
                $redirects->ofVariant('library/other', 'pic.webp'),
                $redirects->of('library/x.png'),
                $redirects->of('library/none.png'),
            ],
        );
    }

    #[Test]
    public function readsTheFileTheConfigNamesElseTheCmsOne(): void
    {
        file_put_contents($this->file, data: '{"a.png":"b.png"}');
        $configured = new ServiceManager(['services' => ['config' => ['storage' => ['redirects' => $this->file]]]]);

        static::assertSame(
            ['b.png', null],
            [
                (new AssetRedirectsFactory())($configured)->of('a.png'),
                (new AssetRedirectsFactory())(new ServiceManager())->of('a.png'),
            ],
        );
    }

    protected function setUp(): void
    {
        $this->file = (string) tempnam(sys_get_temp_dir(), prefix: 'redirects-');
    }

    protected function tearDown(): void
    {
        unlink($this->file);
    }
}
