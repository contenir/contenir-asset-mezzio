<?php

declare(strict_types=1);

namespace Contenir\Asset\Mezzio\Tests\Unit\Container;

use Contenir\Asset\Mezzio\Container\Services;
use Contenir\Asset\Mezzio\Tests\TestAsset\Container\InMemoryContainer;
use Contenir\Asset\Mezzio\Tests\TestAsset\Log\RecordingLogger;
use Contenir\Storage\StorageManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use UnexpectedValueException;

#[Group('unit')]
final class ServicesTest extends TestCase
{
    /**
     * @return array<string, array{mixed, ?string}>
     */
    public static function backendOptionProvider(): array
    {
        return [
            'string'     => ['/media', '/media'],
            'empty'      => ['', null],
            'not string' => [42, null],
            'absent'     => [null, null],
        ];
    }

    /**
     * @return array<string, array{mixed, array<string, mixed>|null}>
     */
    public static function storageConfigProvider(): array
    {
        return [
            'config is not an array'  => ['nope', null],
            'no storage block'        => [[], null],
            'storage is not an array' => [['storage' => 'nope'], null],
            'storage block'           => [['storage' => ['backend' => []]], ['backend' => []]],
        ];
    }

    #[Test]
    #[DataProvider('backendOptionProvider')]
    public function backendOptionReadsANonEmptyStringFromThePrimaryBackend(mixed $value, ?string $expected): void
    {
        $container = new InMemoryContainer([
            'config' => ['storage' => ['backend' => ['local' => ['public_path' => $value]]]],
        ]);

        static::assertSame($expected, Services::backendOption($container, 'public_path'));
    }

    #[Test]
    public function getRejectsAServiceOfTheWrongType(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage(
            'Service "Contenir\Storage\StorageManager" must be an instance of Contenir\Storage\StorageManager, string given.',
        );

        Services::get(new InMemoryContainer([StorageManager::class => 'nope']), StorageManager::class);
    }

    #[Test]
    public function getReturnsAServiceOfTheRequestedType(): void
    {
        $manager = new StorageManager();

        static::assertSame(
            $manager,
            Services::get(new InMemoryContainer([StorageManager::class => $manager]), StorageManager::class),
        );
    }

    #[Test]
    public function loggerFallsBackToANullLogger(): void
    {
        static::assertInstanceOf(NullLogger::class, Services::logger(new InMemoryContainer()));
    }

    #[Test]
    public function loggerIsTheApplicationLogger(): void
    {
        $logger = new RecordingLogger();

        static::assertSame($logger, Services::logger(new InMemoryContainer([LoggerInterface::class => $logger])));
    }

    #[Test]
    public function loggerRejectsAServiceOfTheWrongType(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('must be an instance of Psr\Log\LoggerInterface, string given.');

        Services::logger(new InMemoryContainer([LoggerInterface::class => 'nope']));
    }

    /**
     * @param array<string, mixed>|null $expected
     */
    #[Test]
    #[DataProvider('storageConfigProvider')]
    public function storageConfigReadsTheStorageBlock(mixed $config, ?array $expected): void
    {
        static::assertSame($expected, Services::storageConfig(new InMemoryContainer(['config' => $config])));
    }

    #[Test]
    public function storageVariantsIsEmptyWhenNotAnArray(): void
    {
        static::assertSame(
            [],
            Services::storageVariants(new InMemoryContainer(['config' => ['storage' => ['variants' => 'x']]])),
        );
    }

    #[Test]
    public function storageVariantsReadsTheVariantDeclarations(): void
    {
        $variants = ['thumb' => ['width' => 10, 'height' => 10]];

        static::assertSame(
            $variants,
            Services::storageVariants(new InMemoryContainer(['config' => ['storage' => ['variants' => $variants]]])),
        );
    }
}
