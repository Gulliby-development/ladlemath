<?php

declare(strict_types=1);

namespace Ladlemath\Num;

use InvalidArgumentException;

final class Show
{
    public static function glyphValue(string $glyph): ?Fraction
    {
        return match ($glyph) {
            '¼' => Fraction::of(1, 4),
            '½' => Fraction::of(1, 2),
            '¾' => Fraction::of(3, 4),
            '⅓' => Fraction::of(1, 3),
            '⅔' => Fraction::of(2, 3),
            '⅛' => Fraction::of(1, 8),
            '⅜' => Fraction::of(3, 8),
            '⅝' => Fraction::of(5, 8),
            '⅞' => Fraction::of(7, 8),
            default => null,
        };
    }

    public static function mixed(Fraction $value, bool $glyphs): string
    {
        $negative = $value->num < 0;
        $magnitude = $negative ? -$value->num : $value->num;
        $whole = intdiv($magnitude, $value->den);
        $remainder = $magnitude % $value->den;
        $sign = $negative ? '-' : '';

        if ($remainder === 0) {
            return $sign . (string) $whole;
        }

        if ($glyphs) {
            $glyph = self::glyphFor($remainder, $value->den);
            if ($glyph !== null) {
                return $sign . ($whole === 0 ? '' : (string) $whole) . $glyph;
            }
        }

        $fraction = (string) $remainder . '/' . (string) $value->den;
        $body = $whole === 0 ? $fraction : (string) $whole . ' ' . $fraction;

        return $sign . $body;
    }

    public static function hundredths(Fraction $value): string
    {
        if (100 % $value->den !== 0) {
            throw new InvalidArgumentException('value is not an exact hundredth');
        }

        $negative = $value->num < 0;
        $magnitude = $negative ? -$value->num : $value->num;
        $whole = intdiv($magnitude, $value->den);
        $remainder = $magnitude % $value->den;
        $fractionalHundredths = $remainder * intdiv(100, $value->den);
        $sign = $negative ? '-' : '';

        if ($fractionalHundredths === 0) {
            return $sign . (string) $whole;
        }

        $decimal = str_pad((string) $fractionalHundredths, 2, '0', STR_PAD_LEFT);
        $decimal = rtrim($decimal, '0');

        return $sign . (string) $whole . '.' . $decimal;
    }

    public static function value(Fraction $value, ?string $unit, bool $glyphs): string
    {
        if ($unit === 'g' || $unit === 'ml') {
            return self::mixed($value, false);
        }

        if ($unit === 'kg' || $unit === 'l') {
            return self::hundredths($value);
        }

        return self::mixed($value, $glyphs);
    }

    private static function glyphFor(int $numerator, int $denominator): ?string
    {
        return match ((string) $numerator . '/' . (string) $denominator) {
            '1/4' => '¼',
            '1/2' => '½',
            '3/4' => '¾',
            '1/3' => '⅓',
            '2/3' => '⅔',
            '1/8' => '⅛',
            '3/8' => '⅜',
            '5/8' => '⅝',
            '7/8' => '⅞',
            default => null,
        };
    }
}
