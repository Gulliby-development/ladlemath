<?php

declare(strict_types=1);

namespace Ladlemath\Kitchen;

use InvalidArgumentException;
use Ladlemath\Num\Amount;
use Ladlemath\Num\Fraction;
use Ladlemath\Unit\Units;

final class Conversion
{
    public static function convert(Amount $amount, string $to): ?Amount
    {
        $unit = $amount->unit;

        if ($unit === null) {
            return null;
        }

        if ($to === 'metric') {
            if (self::isMetric($unit)) {
                return null;
            }

            return self::toMetric($amount, $unit);
        }

        if ($to === 'us') {
            if (self::isUs($unit)) {
                return null;
            }

            return self::toUs($amount, $unit);
        }

        throw new InvalidArgumentException('unknown target system');
    }

    private static function toMetric(Amount $amount, string $source): Amount
    {
        $family = Units::family($source);
        $baseUnit = $family === 'volume' ? 'ml' : 'g';
        $largeUnit = $family === 'volume' ? 'l' : 'kg';
        $sourceFactor = Units::factor($source);
        $promotionThreshold = Fraction::of(1000, $sourceFactor);
        $target = $amount->lo->ge($promotionThreshold) ? $largeUnit : $baseUnit;

        return self::scaled($amount, $target, $sourceFactor, Units::factor($target));
    }

    private static function toUs(Amount $amount, string $source): Amount
    {
        $family = Units::family($source);
        $sourceFactor = Units::factor($source);

        if ($family === 'volume') {
            if ($amount->lo->ge(Fraction::of(60, $sourceFactor))) {
                $target = 'cup';
            } elseif ($amount->lo->ge(Fraction::of(15, $sourceFactor))) {
                $target = 'tbsp';
            } else {
                $target = 'tsp';
            }
        } else {
            $target = $amount->lo->ge(Fraction::of(448, $sourceFactor)) ? 'lb' : 'oz';
        }

        return self::scaled($amount, $target, $sourceFactor, Units::factor($target));
    }

    private static function scaled(Amount $amount, string $target, int $numerator, int $denominator): Amount
    {
        $ratio = Fraction::of($numerator, $denominator);
        $targetAmount = new Amount($target, $amount->lo, $amount->hi);

        return $targetAmount->times($ratio);
    }

    private static function isMetric(string $unit): bool
    {
        return match ($unit) {
            'ml', 'l', 'g', 'kg' => true,
            default => false,
        };
    }

    private static function isUs(string $unit): bool
    {
        return match ($unit) {
            'tsp', 'tbsp', 'cup', 'oz', 'lb' => true,
            default => false,
        };
    }
}
