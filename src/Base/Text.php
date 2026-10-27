<?php

declare(strict_types=1);

namespace Ladlemath\Base;

use InvalidArgumentException;

final class Text
{
    /**
     * @return list<string>
     */
    public static function cps(string $text): array
    {
        if ($text === '') {
            return [];
        }

        $codePoints = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);

        if ($codePoints === false) {
            throw new InvalidArgumentException('Text must be valid UTF-8.');
        }

        return $codePoints;
    }

    public static function length(string $text): int
    {
        return count(self::cps($text));
    }

    public static function isUtf8(string $text): bool
    {
        return preg_match('//u', $text) === 1;
    }

    public static function isBlank(?string $character): bool
    {
        return $character === ' ' || $character === "\t";
    }

    public static function isDigit(?string $character): bool
    {
        return $character !== null && preg_match('/\A[0-9]\z/', $character) === 1;
    }

    public static function trimBlanks(string $text): string
    {
        return trim($text, " \t");
    }

    public static function nameKey(string $text): string
    {
        $trimmed = self::trimBlanks($text);
        $collapsed = preg_replace('/[ \t]+/', ' ', $trimmed);

        if ($collapsed === null) {
            return '';
        }

        return strtr($collapsed, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }

    /**
     * @param list<string> $lines
     */
    public static function lines(array $lines): string
    {
        if ($lines === []) {
            return '';
        }

        return implode("\n", $lines) . "\n";
    }

    public static function plural(int $count, string $word): string
    {
        $noun = $count === 1 ? $word : $word . 's';

        return $count . ' ' . $noun;
    }

    public static function shown(string $text): string
    {
        $escaped = str_replace(
            ['\\', '"', "\t", "\r", "\n"],
            ['\\\\', '\\"', '\\t', '\\r', '\\n'],
            $text,
        );

        return '"' . $escaped . '"';
    }
}
