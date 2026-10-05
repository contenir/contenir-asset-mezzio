<?php

declare(strict_types=1);

namespace Contenir\Asset\Mezzio\Tests\TestAsset\Log;

use Override;
use Psr\Log\AbstractLogger;
use Stringable;

use function is_scalar;
use function str_replace;

/**
 * A PSR-3 logger that keeps every record, with its placeholders interpolated.
 */
final class RecordingLogger extends AbstractLogger
{
    /** @var list<array{level: mixed, message: string}> */
    public array $records = [];

    /**
     * @param array<array-key, mixed> $context
     */
    #[Override]
    public function log(mixed $level, string|Stringable $message, array $context = []): void
    {
        $text = (string) $message;
        foreach ($context as $key => $value) {
            $text = str_replace("{{$key}}", is_scalar($value) ? (string) $value : '', $text);
        }

        $this->records[] = ['level' => $level, 'message' => $text];
    }
}
