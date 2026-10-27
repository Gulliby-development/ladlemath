<?php

declare(strict_types=1);

namespace Ladlemath\Num;

use InvalidArgumentException;
use Ladlemath\Base\Text;

final class Scanner
{
    /**
     * @param list<string> $cps
     * @return array{0: Fraction, 1: int}|null
     */
    public static function number(array $cps, int $i): ?array
    {
        if ($i < 0) {
            return null;
        }

        $current = $cps[$i] ?? null;
        $glyph = self::glyph($current);
        if ($glyph !== null) {
            return [$glyph, $i + 1];
        }

        if (!Text::isDigit($current)) {
            if ($current !== '.' || !Text::isDigit($cps[$i + 1] ?? null)) {
                return null;
            }

            [$length, $end] = self::scanDigits($cps, $i + 1);
            if ($length > 4) {
                throw new InvalidArgumentException('decimal run is too long');
            }

            $numerator = self::digitsValue($cps, $i + 1, $end);
            $denominator = self::powerOfTen($length);

            return [Fraction::of($numerator, $denominator), $end];
        }

        [$integerLength, $integerEnd] = self::scanDigits($cps, $i);
        if ($integerLength > 6) {
            throw new InvalidArgumentException('integer run is too long');
        }

        $whole = self::digitsValue($cps, $i, $integerEnd);

        $spaceEnd = self::spacesEnd($cps, $integerEnd);
        if ($spaceEnd > $integerEnd) {
            $mixedFraction = self::fraction($cps, $spaceEnd);
            if ($mixedFraction !== null) {
                return [
                    Fraction::of($whole)->add($mixedFraction[0]),
                    $mixedFraction[1],
                ];
            }

            if ($spaceEnd === $integerEnd + 1) {
                $spacedGlyph = self::glyph($cps[$spaceEnd] ?? null);
                if ($spacedGlyph !== null) {
                    return [
                        Fraction::of($whole)->add($spacedGlyph),
                        $spaceEnd + 1,
                    ];
                }
            }
        } elseif (($cps[$integerEnd] ?? null) === '-') {
            $mixedFraction = self::fraction($cps, $integerEnd + 1);
            if ($mixedFraction !== null) {
                return [
                    Fraction::of($whole)->add($mixedFraction[0]),
                    $mixedFraction[1],
                ];
            }
        }

        $attachedGlyph = self::glyph($cps[$integerEnd] ?? null);
        if ($attachedGlyph !== null) {
            return [
                Fraction::of($whole)->add($attachedGlyph),
                $integerEnd + 1,
            ];
        }

        $fraction = self::fraction($cps, $i);
        if ($fraction !== null) {
            return $fraction;
        }

        if (($cps[$integerEnd] ?? null) === '.' && Text::isDigit($cps[$integerEnd + 1] ?? null)) {
            [$decimalLength, $decimalEnd] = self::scanDigits($cps, $integerEnd + 1);
            if ($decimalLength > 4) {
                throw new InvalidArgumentException('decimal run is too long');
            }

            $decimal = Fraction::of(
                self::digitsValue($cps, $integerEnd + 1, $decimalEnd),
                self::powerOfTen($decimalLength),
            );

            return [Fraction::of($whole)->add($decimal), $decimalEnd];
        }

        return [Fraction::of($whole), $integerEnd];
    }

    /**
     * @param list<string> $cps
     * @return array{0: Fraction, 1: int}|null
     */
    public static function fraction(array $cps, int $i): ?array
    {
        if ($i < 0 || !Text::isDigit($cps[$i] ?? null)) {
            return null;
        }

        [$numeratorLength, $slashIndex] = self::scanDigits($cps, $i);
        if (($cps[$slashIndex] ?? null) !== '/' || !Text::isDigit($cps[$slashIndex + 1] ?? null)) {
            return null;
        }

        if ($numeratorLength > 4) {
            throw new InvalidArgumentException('fraction numerator run is too long');
        }

        [$denominatorLength, $end] = self::scanDigits($cps, $slashIndex + 1);
        if ($denominatorLength > 4) {
            throw new InvalidArgumentException('fraction denominator run is too long');
        }

        $numerator = self::digitsValue($cps, $i, $slashIndex);
        $denominator = self::digitsValue($cps, $slashIndex + 1, $end);
        if ($denominator === 0) {
            throw new InvalidArgumentException('fraction denominator must not be zero');
        }

        return [Fraction::of($numerator, $denominator), $end];
    }

    public static function starts(?string $c, ?string $next): bool
    {
        return Text::isDigit($c) || self::glyph($c) !== null || ($c === '.' && Text::isDigit($next));
    }

    /**
     * @param list<string> $cps
     * @return array{0: int, 1: int}
     */
    private static function scanDigits(array $cps, int $i): array
    {
        $end = $i;
        $count = count($cps);
        while ($end < $count && Text::isDigit($cps[$end])) {
            $end++;
        }

        return [$end - $i, $end];
    }

    /**
     * @param list<string> $cps
     */
    private static function digitsValue(array $cps, int $start, int $end): int
    {
        $value = 0;
        for ($index = $start; $index < $end; $index++) {
            $digit = $cps[$index];
            $value = ($value * 10) + (ord($digit) - ord('0'));
        }

        return $value;
    }

    /**
     * @param list<string> $cps
     */
    private static function spacesEnd(array $cps, int $i): int
    {
        $end = $i;
        $count = count($cps);
        while ($end < $count && $cps[$end] === ' ') {
            $end++;
        }

        return $end;
    }

    private static function powerOfTen(int $power): int
    {
        $value = 1;
        for ($index = 0; $index < $power; $index++) {
            $value *= 10;
        }

        return $value;
    }

    private static function glyph(?string $character): ?Fraction
    {
        return $character === null ? null : Show::glyphValue($character);
    }
}
