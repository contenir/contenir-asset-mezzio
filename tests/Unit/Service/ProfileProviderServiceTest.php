<?php

declare(strict_types=1);

namespace Contenir\Asset\Mezzio\Tests\Unit\Service;

use Contenir\Asset\Mezzio\Profile\Profile;
use Contenir\Asset\Mezzio\Service\ProfileProviderService;
use Contenir\Storage\Variant;
use Contenir\Storage\VariantFit;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;

#[Group('unit')]
final class ProfileProviderServiceTest extends TestCase
{
    #[Test]
    public function compilesDimensionFamilyToProfileAndExpandedVariants(): void
    {
        $provider = new ProfileProviderService([
            'card' => [
                'fit'        => 'cover',
                'quality'    => 75,
                'formats'    => ['AVIF', 'WebP'],
                'sizes'      => '(min-width: 1024px) 33vw, 100vw',
                'dimensions' => ['320x320', '480x480', '768x768'],
            ],
        ]);

        $profile = $provider->get('card');
        static::assertInstanceOf(Profile::class, $profile);
        static::assertSame('(min-width: 1024px) 33vw, 100vw', $profile->sizes);
        static::assertSame(['avif', 'webp'], $profile->formats);
        static::assertSame(
            ['card-320', 'card-480', 'card-768'],
            array_map(static fn(Variant $variant): string => $variant->name, $profile->variants),
        );

        $variant = $provider->variant('card-480');
        static::assertNotNull($variant);
        static::assertSame(480, $variant->width);
        static::assertSame(480, $variant->height);
        static::assertSame(VariantFit::Cover, $variant->fit);
    }

    #[Test]
    public function flatStandaloneVariantIsRegisteredButNotAProfile(): void
    {
        $provider = new ProfileProviderService([
            'admin-thumb' => ['width' => 180, 'height' => 180, 'fit' => 'contain'],
        ]);

        static::assertFalse($provider->has('admin-thumb'));
        $variant = $provider->variant('admin-thumb');
        static::assertNotNull($variant);
        static::assertSame(180, $variant->width);
    }

    #[Test]
    public function getExcludesPreviewVariantFromResponsiveList(): void
    {
        $profile = $this->provider()->get('tile');

        static::assertNotNull($profile);
        $names = array_map(static fn(Variant $variant): string => $variant->name, $profile->variants);
        static::assertSame(['tile-320', 'tile-640'], $names);
    }

    #[Test]
    public function getReturnsNullForUnknownProfile(): void
    {
        static::assertNull($this->provider()->get('missing'));
    }

    #[Test]
    public function getReturnsTypedProfileWithLowercasedFormats(): void
    {
        $profile = $this->provider()->get('tile');

        static::assertInstanceOf(Profile::class, $profile);
        static::assertSame('tile', $profile->key);
        static::assertSame('(min-width: 768px) 33vw, 100vw', $profile->sizes);
        static::assertSame(['avif', 'webp'], $profile->formats);
    }

    #[Test]
    public function hasReportsKnownProfiles(): void
    {
        $provider = $this->provider();

        static::assertTrue($provider->has('tile'));
        static::assertFalse($provider->has('missing'));
    }

    #[Test]
    public function ignoresMalformedDeclarations(): void
    {
        $provider = new ProfileProviderService([
            'broken'  => 'nope',
            'nothing' => ['sizes' => '100vw'],
            'legacy'  => [
                'variants' => [
                    'ok'  => ['width' => '120', 'height' => ['x'], 'fit' => 'FILL', 'quality' => '60'],
                    'bad' => 'nope',
                ],
                'sizes'    => ['not', 'a', 'string'],
                'formats'  => 'AVIF',
            ],
            'flat'    => ['width' => 10, 'fit' => ['x']],
            'scalar'  => ['variants' => 'nope'],
        ]);

        $ok = $provider->variant('ok');
        static::assertSame([120, 0, VariantFit::Fill, 60], [$ok?->width, $ok?->height, $ok?->fit, $ok?->quality]);
        static::assertNull($provider->variant('bad'));
        static::assertSame(['', ['avif']], [$provider->get('legacy')?->sizes, $provider->get('legacy')?->formats]);
        static::assertSame(VariantFit::Cover, $provider->variant('flat')?->fit);
        static::assertFalse($provider->has('nothing'));
        static::assertSame([], $provider->get('scalar')?->variants);
    }

    #[Test]
    public function legacyVariantWithoutAWidthHasZeroWidth(): void
    {
        $provider = new ProfileProviderService(['legacy' => ['variants' => ['tall' => ['height' => 400]]]]);

        static::assertSame(0, $provider->variant('tall')?->width);
    }

    #[Test]
    public function malformedLegacyVariantDoesNotHideTheVariantsAfterIt(): void
    {
        $provider = new ProfileProviderService([
            'legacy' => ['variants' => ['bad' => 'nope', 'good' => ['width' => 320]]],
        ]);

        static::assertSame(320, $provider->variant('good')?->width);
    }

    #[Test]
    public function nonArrayProfileIsSkipped(): void
    {
        static::assertFalse($this->provider()->has('bogus'));
    }

    #[Test]
    public function previewRoleFamilyRegistersVariantsWithoutProfile(): void
    {
        $provider = new ProfileProviderService([
            'thumb' => [
                'role'       => 'preview',
                'fit'        => 'contain',
                'dimensions' => ['180x180'],
            ],
        ]);

        static::assertFalse($provider->has('thumb'));
        static::assertNotNull($provider->variant('thumb-180'));
    }

    #[Test]
    public function variantDefaultsFitToCoverAndNullQuality(): void
    {
        $variant = $this->provider()->variant('tile-640');

        static::assertNotNull($variant);
        static::assertSame(VariantFit::Cover, $variant->fit);
        static::assertNull($variant->quality);
    }

    #[Test]
    public function variantMapsContainFitAndAutoHeight(): void
    {
        $variant = $this->provider()->variant('gallery-1600');

        static::assertNotNull($variant);
        static::assertSame(VariantFit::Contain, $variant->fit);
        static::assertSame(0, $variant->height);
    }

    #[Test]
    public function variantMapsDimensionsFitAndQuality(): void
    {
        $variant = $this->provider()->variant('tile-320');

        static::assertNotNull($variant);
        static::assertSame(320, $variant->width);
        static::assertSame(240, $variant->height);
        static::assertSame(VariantFit::Cover, $variant->fit);
        static::assertSame(80, $variant->quality);
    }

    #[Test]
    public function variantReturnsFlatDefinitionIncludingPreview(): void
    {
        $preview = $this->provider()->variant('admin-thumb');

        static::assertInstanceOf(Variant::class, $preview);
        static::assertSame(180, $preview->width);
    }

    #[Test]
    public function variantReturnsNullForUnknownName(): void
    {
        static::assertNull($this->provider()->variant('nope'));
    }

    private function provider(): ProfileProviderService
    {
        return new ProfileProviderService([
            'tile'    => [
                'sizes'    => '(min-width: 768px) 33vw, 100vw',
                'formats'  => ['AVIF', 'WebP'],
                'variants' => [
                    'admin-thumb' => ['width' => 180, 'height' => 180, 'fit' => 'contain'],
                    'tile-320'    => ['width' => 320, 'height' => 240, 'fit' => 'cover', 'quality' => 80],
                    'tile-640'    => ['width' => 640, 'height' => 480, 'fit' => 'cover'],
                ],
            ],
            'gallery' => [
                'variants' => [
                    'gallery-1600' => ['width' => 1600, 'fit' => 'contain'],
                ],
            ],
            'bogus'   => 'not-an-array',
        ]);
    }
}
