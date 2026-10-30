<?php

declare(strict_types=1);

namespace Contenir\Asset\Mezzio\View;

use function htmlspecialchars;
use function sprintf;

use const ENT_HTML5;
use const ENT_QUOTES;
use const ENT_SUBSTITUTE;

/**
 * Writes elements with escaped attributes.
 *
 * @internal
 */
final class AssetMarkup
{
    /**
     * @param array<string, string> $attributes
     */
    public static function element(string $name, array $attributes, ?string $content = null): string
    {
        $written = '';
        foreach ($attributes as $attribute => $value) {
            $written .= sprintf(' %s="%s"', self::escape($attribute), self::escape($value));
        }

        return null === $content ? "<{$name}{$written}>" : "<{$name}{$written}>{$content}</{$name}>";
    }

    /**
     * A nonce attribute, or none for no nonce.
     *
     * @return array<string, string>
     */
    public static function nonce(?string $nonce): array
    {
        return null === $nonce || '' === $nonce ? [] : ['nonce' => $nonce];
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, encoding: 'UTF-8');
    }
}
