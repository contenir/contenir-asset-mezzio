<?php

declare(strict_types=1);

namespace Contenir\Asset\Mezzio\Tests\Unit\Service;

use Contenir\Asset\Mezzio\Service\OnDemandVariantResolver;
use Contenir\Asset\Mezzio\Tests\TestAsset\Storage\FakeOnDemandStorage;
use Contenir\Storage\Adapter\InMemoryStorage;
use Contenir\Storage\OnDemandVariantGeneratorInterface;
use Contenir\Storage\StorageInterface;
use Contenir\Storage\StorageManager;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class OnDemandVariantResolverTest extends TestCase
{
    #[Test]
    public function generatesViaTheFirstBackendThatRecognisesTheKey(): void
    {
        $declining = $this->createStubForIntersectionOfInterfaces([
            StorageInterface::class,
            OnDemandVariantGeneratorInterface::class,
        ]);
        $declining->method('generateForKey')->willReturn(null);
        $owner   = new FakeOnDemandStorage([]);
        $manager = new StorageManager();
        $manager->register('local', new InMemoryStorage());
        $manager->register('other', $declining);
        $manager->register('r2', $owner);

        $url = (new OnDemandVariantResolver($manager))->generate('gallery/cat__card-320.avif');

        static::assertSame('https://cdn.test/gallery/cat__card-320.avif', $url);
        static::assertSame(['gallery/cat__card-320.avif'], $owner->generated);
    }

    #[Test]
    public function returnsNullWhenNoBackendCanGenerateOnDemand(): void
    {
        $manager = new StorageManager();
        $manager->register('local', new InMemoryStorage());

        static::assertNull((new OnDemandVariantResolver($manager))->generate('gallery/cat__card-320.avif'));
    }
}
