<?php

declare(strict_types=1);

namespace Ladlemath\Kitchen;

use Ladlemath\Num\Amount;
use Ladlemath\Num\Fraction;
use Ladlemath\Num\Show;

final class Quantity
{
    public static function finish(Amount $amount, bool $glyphs): string
    {
        $rounded = new Amount(
            $amount->unit,
            Rounding::round($amount->unit, $amount->lo),
            $amount->hi === null ? null : Rounding::round($amount->unit, $amount->hi),
        );

        return self::render($rounded, $glyphs);
    }

    public static function render(Amount $amount, bool $glyphs): string
    {
        $top = $amount->hi ?? $amount->lo;
        $value = Show::value($amount->lo, $amount->unit, $glyphs);

        if ($amount->hi !== null && $amount->lo->compare($amount->hi) !== 0) {
            $value .= ' to ' . Show::value($amount->hi, $amount->unit, $glyphs);
        }

        if ($amount->unit === null) {
            return $value;
        }

        $unit = $amount->unit;
        if ($unit === 'cup' && $top->compare(Fraction::of(1)) > 0) {
            $unit = 'cups';
        }

        return $value . ' ' . $unit;
    }
}
