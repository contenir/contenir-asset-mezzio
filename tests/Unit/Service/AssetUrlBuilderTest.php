<?php

declare(strict_types=1);

namespace Contenir\Asset\Mezzio\Tests\Unit\Service;

use Contenir\Asset\Mezzio\Service\AssetUrlBuilder;
use Contenir\Storage\Variant;
use Contenir\Storage\VariantFit;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class AssetUrlBuilderTest extends TestCase
{
    #[Test]
    public function anEmptyFormatMeansTheSourceFormat(): void
    {
        static::assertSame('/docs/_variant/thumb/a.jpg', (new AssetUrlBuilder(''))->variantUrl(
            'docs/a.jpg',
            'thumb',
            '',
        ));
    }

    #[Test]
    public function collapsesASlashLeftOverAfterStrippingThePublicBase(): void
    {
        static::assertSame('/media/a.jpg', (new AssetUrlBuilder('/media'))->originalUrl('/media//a.jpg'));
    }

    #[Test]
    public function ignoresATrailingSlashOnThePublicBase(): void
    {
        static::assertSame('/media/a/photo.jpg', (new AssetUrlBuilder('/media/'))->originalUrl('a/photo.jpg'));
    }

    #[Test]
    public function keepsAPathThatMerelyStartsWithThePublicBaseName(): void
    {
        static::assertSame(
            '/media/mediafiles/a.jpg',
            (new AssetUrlBuilder('/media'))->originalUrl('mediafiles/a.jpg'),
        );
    }

    #[Test]
    public function localOriginalUrlPercentEncodesPathSegments(): void
    {
        $builder = new AssetUrlBuilder('');

        static::assertSame('/a/my%20photo%20%281%29.jpg', $builder->originalUrl('/a/my photo (1).jpg'));
    }

    #[Test]
    public function localSrcsetStaysParseableWhenFilenamesContainSpaces(): void
    {
        /**
         * An unencoded space ends the candidate URL as far as the srcset
         * tokeniser is concerned, and the browser drops the whole attribute --
         * so every candidate has to survive as a single token.
         */
        $builder  = new AssetUrlBuilder('');
        $variants = [
            new Variant('card-320', 320, 160, VariantFit::Cover),
            new Variant('card-640', 640, 320, VariantFit::Cover),
        ];

        static::assertSame(
            '/a/_variant/card-320/my%20photo.jpg 320w, /a/_variant/card-640/my%20photo.jpg 640w',
            $builder->srcset('/a/my photo.jpg', $variants),
        );
    }

    #[Test]
    public function localVariantUrlPercentEncodesPathSegments(): void
    {
        $builder = new AssetUrlBuilder('');

        static::assertSame(
            '/a/_variant/card-320/my%20photo%20%281%29.jpg',
            $builder->variantUrl('/a/my photo (1).jpg', 'card-320'),
        );
    }

    #[Test]
    public function originalUrlHonoursPublicBase(): void
    {
        $builder = new AssetUrlBuilder('https://cdn.example.com');

        static::assertSame('https://cdn.example.com/a/photo.jpg', $builder->originalUrl('/a/photo.jpg'));
    }

    #[Test]
    public function originalUrlIsRootRelativeWithEmptyBase(): void
    {
        $builder = new AssetUrlBuilder('');

        static::assertSame('/a/photo.jpg', $builder->originalUrl('/a/photo.jpg'));
    }

    #[Test]
    public function r2BackendBuildsSiblingKeyKeepingSourceExtension(): void
    {
        $builder = new AssetUrlBuilder('https://cdn.example.com', 'r2');

        static::assertSame(
            'https://cdn.example.com/asset/library/photo__card.jpg',
            $builder->variantUrl('asset/library/photo.jpg', 'card'),
        );
    }

    #[Test]
    public function r2BackendPercentEncodesPathSegments(): void
    {
        $builder = new AssetUrlBuilder('https://cdn.example.com', 'r2');

        static::assertSame(
            'https://cdn.example.com/a/my%20photo__card.jpg',
            $builder->variantUrl('a/my photo.jpg', 'card'),
        );
    }

    #[Test]
    public function r2BackendSrcsetEmitsSiblingKeysWithWidthDescriptors(): void
    {
        $builder  = new AssetUrlBuilder('https://cdn.example.com', 'r2');
        $variants = [
            new Variant('card-320', 320, 320, VariantFit::Cover),
            new Variant('card-640', 640, 640, VariantFit::Cover),
        ];

        static::assertSame(
            'https://cdn.example.com/a/photo__card-320.avif 320w, '
                . 'https://cdn.example.com/a/photo__card-640.avif 640w',
            $builder->srcset('a/photo.jpg', $variants, 'avif'),
        );
    }

    #[Test]
    public function r2BackendSwapsExtensionForFormat(): void
    {
        $builder = new AssetUrlBuilder('https://cdn.example.com', 'r2');

        static::assertSame(
            'https://cdn.example.com/asset/library/photo__card.avif',
            $builder->variantUrl('asset/library/photo.jpg', 'card', 'avif'),
        );
    }

    #[Test]
    public function r2BackendUsesObjectKeyVerbatimWithoutPrefixStripping(): void
    {
        /**
         * Unlike local, the CDN host is the public base, so the R2 object key is
         * not a public-path prefix to strip.
         */
        $builder = new AssetUrlBuilder('https://cdn.example.com', 'r2');

        static::assertSame(
            'https://cdn.example.com/photo__card.webp',
            $builder->variantUrl('/photo.jpg', 'card', 'webp'),
        );
    }

    #[Test]
    public function siblingKeyOfAnExtensionlessObjectStaysExtensionless(): void
    {
        static::assertSame(
            'https://cdn.test/docs/readme__thumb',
            (new AssetUrlBuilder('https://cdn.test', 's3'))->variantUrl('docs/readme', 'thumb'),
        );
    }

    #[Test]
    public function siblingSchemeUsesTheStoredKeyVerbatimEvenWhenItRepeatsTheBase(): void
    {
        $builder = new AssetUrlBuilder('/bucket', 's3');

        static::assertSame('/bucket/bucket/a__thumb.jpg', $builder->variantUrl('bucket/a.jpg', 'thumb'));
    }

    #[Test]
    public function srcsetAppliesFormat(): void
    {
        $builder  = new AssetUrlBuilder('');
        $variants = [new Variant('tile-320', 320, 240, VariantFit::Cover)];

        static::assertSame(
            '/a/_variant/tile-320/photo.webp 320w',
            $builder->srcset('/a/photo.jpg', $variants, 'webp'),
        );
    }

    #[Test]
    public function srcsetEmitsWidthDescriptorPerVariant(): void
    {
        $builder  = new AssetUrlBuilder('');
        $variants = [
            new Variant('tile-320', 320, 240, VariantFit::Cover),
            new Variant('tile-640', 640, 480, VariantFit::Cover),
        ];

        static::assertSame(
            '/a/_variant/tile-320/photo.jpg 320w, /a/_variant/tile-640/photo.jpg 640w',
            $builder->srcset('/a/photo.jpg', $variants),
        );
    }

    #[Test]
    public function stripsPublicBasePrefixToAvoidDoubling(): void
    {
        $builder = new AssetUrlBuilder('/asset/library');

        static::assertSame(
            '/asset/library/a/_variant/tile-320/photo.jpg',
            $builder->variantUrl('/asset/library/a/photo.jpg', 'tile-320'),
        );
    }

    #[Test]
    public function variantUrlForRootLevelFile(): void
    {
        $builder = new AssetUrlBuilder('');

        static::assertSame('/_variant/tile-320/photo.jpg', $builder->variantUrl('photo.jpg', 'tile-320'));
    }

    #[Test]
    public function variantUrlInsertsKeyedVariantDir(): void
    {
        $builder = new AssetUrlBuilder('');

        static::assertSame('/a/_variant/tile-320/photo.jpg', $builder->variantUrl('/a/photo.jpg', 'tile-320'));
    }

    #[Test]
    public function variantUrlSwapsExtensionForFormat(): void
    {
        $builder = new AssetUrlBuilder('');

        static::assertSame('/a/_variant/tile-320/photo.avif', $builder->variantUrl('/a/photo.jpg', 'tile-320', 'avif'));
    }
}
