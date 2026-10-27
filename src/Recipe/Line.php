<?php

declare(strict_types=1);

namespace Ladlemath\Recipe;

use InvalidArgumentException;
use Ladlemath\Base\Text;
use Ladlemath\Model\Problem;
use Ladlemath\Num\Amount;
use Ladlemath\Num\Fraction;
use Ladlemath\Num\Scanner;
use Ladlemath\Unit\Units;

final class Line
{
    public static function parse(string $line, int $number): Entry|Problem
    {
        $cps = Text::cps($line);
        $count = count($cps);
        $start = self::skipBlanks($cps, 0);
        $startsQuantity = Scanner::starts($cps[$start] ?? null, $cps[$start + 1] ?? null);
        $amount = null;
        $nameStart = 0;

        if ($startsQuantity) {
            try {
                $first = Scanner::number($cps, $start);
                if ($first === null) {
                    return new Problem($number, $start + 1, 'LM001', 'invalid quantity');
                }

                [$lo, $quantityEnd] = $first;
                [$hi, $rangeEnd] = self::rangeAfter($cps, $quantityEnd);
                if ($hi !== null) {
                    if ($hi->compare($lo) <= 0) {
                        return new Problem($number, $start + 1, 'LM002', 'range is not ascending');
                    }

                    $quantityEnd = $rangeEnd;
                } elseif ($lo->isZero()) {
                    return new Problem($number, $start + 1, 'LM006', 'quantity is zero');
                }
            } catch (InvalidArgumentException) {
                return new Problem($number, $start + 1, 'LM001', 'invalid quantity');
            }

            $nameStart = self::skipBlanks($cps, $quantityEnd);
            $unit = self::unitAt($cps, $nameStart);
            if ($unit !== null) {
                [$canonicalUnit, $unitEnd] = $unit;
                $nameStart = self::skipBlanks($cps, $unitEnd);
                if (self::hasOfWord($cps, $nameStart)) {
                    $nameStart = self::skipBlanks($cps, $nameStart + 2);
                }
            } else {
                $canonicalUnit = null;
            }

            $amount = new Amount($canonicalUnit, $lo, $hi);
        }

        $tail = implode('', array_slice($cps, $nameStart, $count - $nameStart));
        $parts = explode(',', $tail, 2);
        $name = Text::trimBlanks($parts[0]);
        $note = Text::trimBlanks($parts[1] ?? '');

        if ($name === '') {
            return new Problem($number, self::missingNameColumn($cps), 'LM003', 'missing ingredient name');
        }

        return new Entry(
            $number,
            $amount,
            $name,
            $note,
            Text::trimBlanks($line),
        );
    }

    /**
     * @param list<string> $cps
     * @return array{0: ?Fraction, 1: int}
     */
    private static function rangeAfter(array $cps, int $quantityEnd): array
    {
        $connectorStart = self::skipBlanks($cps, $quantityEnd);
        $connector = $cps[$connectorStart] ?? null;
        $secondStart = $connectorStart;

        if ($connector === '-' || $connector === '–') {
            $secondStart = self::skipBlanks($cps, $connectorStart + 1);
        } elseif (
            $connectorStart > $quantityEnd
            && $connector === 't'
            && ($cps[$connectorStart + 1] ?? null) === 'o'
            && Text::isBlank($cps[$connectorStart + 2] ?? null)
        ) {
            $secondStart = self::skipBlanks($cps, $connectorStart + 3);
        } else {
            return [null, $quantityEnd];
        }

        if (!Scanner::starts($cps[$secondStart] ?? null, $cps[$secondStart + 1] ?? null)) {
            return [null, $quantityEnd];
        }

        $second = Scanner::number($cps, $secondStart);
        if ($second === null) {
            return [null, $quantityEnd];
        }

        return [$second[0], $second[1]];
    }

    /**
     * @param list<string> $cps
     */
    private static function skipBlanks(array $cps, int $index): int
    {
        $count = count($cps);
        while ($index < $count && Text::isBlank($cps[$index])) {
            $index++;
        }

        return $index;
    }

    /**
     * @param list<string> $cps
     * @return array{0: string, 1: int}|null
     */
    private static function unitAt(array $cps, int $start): ?array
    {
        $count = count($cps);
        $index = $start;

        while ($index < $count && self::isAsciiLetter($cps[$index])) {
            $index++;
        }

        if ($index === $start) {
            return null;
        }

        $word = implode('', array_slice($cps, $start, $index - $start));
        if (($cps[$index] ?? null) === '.') {
            $index++;
        }

        $next = $cps[$index] ?? null;
        if ($next !== null && !Text::isBlank($next) && $next !== ',') {
            return null;
        }

        $unit = Units::lookup($word);

        return $unit === null ? null : [$unit, $index];
    }

    /**
     * @param list<string> $cps
     */
    private static function hasOfWord(array $cps, int $start): bool
    {
        return strtolower($cps[$start] ?? '') === 'o'
            && strtolower($cps[$start + 1] ?? '') === 'f'
            && Text::isBlank($cps[$start + 2] ?? null);
    }

    private static function isAsciiLetter(string $character): bool
    {
        return preg_match('/\A[A-Za-z]\z/', $character) === 1;
    }

    /**
     * @param list<string> $cps
     */
    private static function missingNameColumn(array $cps): int
    {
        for ($index = count($cps) - 1; $index >= 0; $index--) {
            if (!Text::isBlank($cps[$index])) {
                return $index + 2;
            }
        }

        return 1;
    }
}
