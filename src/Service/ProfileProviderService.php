<?php

declare(strict_types=1);

namespace Contenir\Asset\Mezzio\Service;

use Contenir\Asset\Mezzio\Profile\Profile;
use Contenir\Storage\Config\VariantProfile;
use Contenir\Storage\Variant;
use Contenir\Storage\VariantFit;
use InvalidArgumentException;

use function array_key_exists;
use function is_array;
use function is_scalar;
use function strtolower;

/**
 * Exposes the art-directed image profiles as typed {@see Profile} / {@see Variant}
 * objects for the front-end helpers.
 *
 * A profile declared with a `dimensions` ladder is compiled by the shared
 * {@see VariantProfile} — the same declaration the generator materialises — so the
 * family lives in a single place. The legacy form (an explicit `variants` map) is
 * still accepted during migration, as is a flat standalone variant (e.g. the
 * `admin-thumb` preview) which is registered for lookup but never exposed as a
 * responsive profile.
 *
 * Variant names are globally unique across profiles, so a bare name — all a
 * request URL carries — resolves to exactly one definition.
 *
 * @api
 *
 * @mago-expect lint:cyclomatic-complexity Accepts the ladder, legacy and flat declaration forms during migration.
 * @mago-expect lint:kan-defect Accepts the ladder, legacy and flat declaration forms during migration.
 */
final class ProfileProviderService
{
    /** The CMS preview variant; never emitted in front-end srcset/sources. */
    public const string PREVIEW_VARIANT = 'admin-thumb';

    /** @var array<string, Profile> */
    private array $profiles = [];

    /** @var array<string, Variant> Flat name => variant, across every profile. */
    private array $variants = [];

    /**
     * @param array<array-key, mixed> $profiles Art-directed variant declarations.
     *
     * @throws InvalidArgumentException If a `dimensions` ladder is malformed.
     *
     * @mago-expect analysis:mixed-assignment Profile declarations are untyped config; each is validated here.
     */
    public function __construct(array $profiles)
    {
        foreach ($profiles as $key => $config) {
            if (! is_array($config)) {
                continue;
            }

            $key = (string) $key;
            match (true) {
                null !== ($config['dimensions'] ?? null) => $this->addFamily($key, $config),
                null !== ($config['variants'] ?? null) => $this->addLegacyProfile($key, $config),
                /**
                 * A flat standalone variant (e.g. the admin-thumb preview) is
                 * registered for lookup, but is never a responsive profile.
                 */
                null !== ($config['width'] ?? null) => $this->variants[$key] = self::buildVariant($key, $config),
                default => null,
            };
        }
    }

    /**
     * @param array<array-key, mixed> $config
     *
     * @mago-expect analysis:mixed-assignment Variant declarations are untyped config; validated here.
     */
    private static function buildVariant(string $name, array $config): Variant
    {
        $quality = $config['quality'] ?? null;

        return new Variant(
            $name,
            self::int($config['width'] ?? 0),
            self::int($config['height'] ?? 0),
            match (strtolower(self::string($config['fit'] ?? 'cover'))) {
                'contain' => VariantFit::Contain,
                'fill'    => VariantFit::Fill,
                default   => VariantFit::Cover,
            },
            [],
            null === $quality ? null : self::int($quality),
        );
    }

    /**
     * @param array<array-key, mixed> $config
     *
     * @return list<string>
     *
     * @mago-expect analysis:mixed-assignment Format lists are untyped config; each entry is cast here.
     */
    private static function formats(array $config): array
    {
        $declared = $config['formats'] ?? [];
        $formats  = [];
        foreach (is_array($declared) ? $declared : [$declared] as $format) {
            $formats[] = strtolower(self::string($format));
        }

        return $formats;
    }

    private static function int(mixed $value): int
    {
        return is_scalar($value) ? (int) $value : 0;
    }

    private static function string(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    public function get(string $key): ?Profile
    {
        return $this->profiles[$key] ?? null;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->profiles);
    }

    public function variant(string $name): ?Variant
    {
        return $this->variants[$name] ?? null;
    }

    /**
     * @param array<array-key, mixed> $config
     *
     * @throws InvalidArgumentException If the ladder is malformed.
     */
    private function addFamily(string $key, array $config): void
    {
        /** @var array<string, mixed> $config Config keys are strings. */
        $profile = VariantProfile::fromArray($key, $config);
        foreach ($profile->variants as $variant) {
            $this->variants[$variant->name] = $variant;
        }

        if ($profile->isPreview) {
            return;
        }

        $this->profiles[$key] = new Profile($key, $profile->sizes, self::formats($config), $profile->variants);
    }

    /**
     * @param array<array-key, mixed> $config
     *
     * @mago-expect analysis:mixed-assignment Legacy variant maps are untyped config; each entry is validated here.
     */
    private function addLegacyProfile(string $key, array $config): void
    {
        $declared   = $config['variants'] ?? null;
        $responsive = [];
        foreach (is_array($declared) ? $declared : [] as $name => $variantConfig) {
            $name = (string) $name;
            if (! is_array($variantConfig)) {
                continue;
            }

            $variant               = self::buildVariant($name, $variantConfig);
            $this->variants[$name] = $variant;
            if (self::PREVIEW_VARIANT !== $name) {
                $responsive[] = $variant;
            }
        }

        $sizes                = $config['sizes'] ?? '';
        $this->profiles[$key] = new Profile(
            $key,
            is_scalar($sizes) ? (string) $sizes : '',
            self::formats($config),
            $responsive,
        );
    }
}
