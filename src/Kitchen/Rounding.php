<?php

declare(strict_types=1);

namespace Ladlemath\Kitchen;

use InvalidArgumentException;
use Ladlemath\Num\Amount;
use Ladlemath\Num\Fraction;

final class Rounding
{
    public static function step(?string $unit, Fraction $v): Fraction
    {
        return match ($unit) {
            'tsp', 'tbsp' => self::below($v, 1, 6, Fraction::of(1, 8), Fraction::of(1, 4), Fraction::of(1, 2)),
            'cup' => self::below($v, 1, 4, Fraction::of(1, 8), Fraction::of(1, 4), Fraction::of(1, 2)),
            null => self::below($v, 1, 10, Fraction::of(1, 4), Fraction::of(1, 2), Fraction::of(1)),
            'g', 'ml' => self::below($v, 10, 100, Fraction::of(1), Fraction::of(5), Fraction::of(10)),
            'kg', 'l' => Fraction::of(1, 20),
            'oz' => $v->lt(Fraction::of(4)) ? Fraction::of(1, 4) : Fraction::of(1, 2),
            'lb' => Fraction::of(1, 4),
            default => throw new InvalidArgumentException('unknown unit'),
        };
    }

    public static function round(?string $unit, Fraction $v): Fraction
    {
        if ($v->isZero()) {
            return Fraction::of(0);
        }

        $step = self::step($unit, $v);

        // Fractions are bounded by 1,000,000,000 and step denominators by 20,
        // so these products stay below 20,000,000,000 on supported 64-bit PHP.
        $numerator = $v->num * $step->den;
        $denominator = $v->den * $step->num;
        $index = intdiv($numerator, $denominator);
        $remainder = $numerator % $denominator;

        if ($remainder < 0) {
            --$index;
            $remainder += $denominator;
        }

        if (2 * $remainder >= $denominator) {
            ++$index;
        }

        if ($v->num > 0 && $index < 1) {
            $index = 1;
        }

        return Fraction::of($index * $step->num, $step->den);
    }

    public static function promote(Amount $amount): Amount
    {
        $unit = $amount->unit;
        $promoted = $amount;

        while (true) {
            $next = match ($unit) {
                'tsp' => $promoted->lo->ge(Fraction::of(3)) ? ['tbsp', 3] : null,
                'tbsp' => $promoted->lo->ge(Fraction::of(4)) ? ['cup', 16] : null,
                'g' => $promoted->lo->ge(Fraction::of(1000)) ? ['kg', 1000] : null,
                'ml' => $promoted->lo->ge(Fraction::of(1000)) ? ['l', 1000] : null,
                'oz' => $promoted->lo->ge(Fraction::of(16)) ? ['lb', 16] : null,
                default => null,
            };

            if ($next === null) {
                return $promoted;
            }

            [$unit, $divisor] = $next;
            $promoted = $promoted->dividedBy(Fraction::of($divisor), $unit);
        }
    }

    private static function below(
        Fraction $value,
        int $firstThreshold,
        int $secondThreshold,
        Fraction $firstStep,
        Fraction $secondStep,
        Fraction $lastStep,
    ): Fraction {
        if ($value->lt(Fraction::of($firstThreshold))) {
            return $firstStep;
        }

        if ($value->lt(Fraction::of($secondThreshold))) {
            return $secondStep;
        }

        return $lastStep;
    }
}
