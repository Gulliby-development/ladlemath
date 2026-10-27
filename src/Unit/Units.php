<?php

declare(strict_types=1);

namespace Ladlemath\Unit;

use InvalidArgumentException;

final class Units
{
    /** @var array<string, string> */
    private const ALIASES = [
        'tsp' => 'tsp',
        'tsps' => 'tsp',
        'teaspoon' => 'tsp',
        'teaspoons' => 'tsp',
        'tbsp' => 'tbsp',
        'tbsps' => 'tbsp',
        'tbs' => 'tbsp',
        'tablespoon' => 'tbsp',
        'tablespoons' => 'tbsp',
        'c' => 'cup',
        'cup' => 'cup',
        'cups' => 'cup',
        'ml' => 'ml',
        'milliliter' => 'ml',
        'milliliters' => 'ml',
        'millilitre' => 'ml',
        'millilitres' => 'ml',
        'l' => 'l',
        'liter' => 'l',
        'liters' => 'l',
        'litre' => 'l',
        'litres' => 'l',
        'g' => 'g',
        'gram' => 'g',
        'grams' => 'g',
        'kg' => 'kg',
        'kilogram' => 'kg',
        'kilograms' => 'kg',
        'oz' => 'oz',
        'ounce' => 'oz',
        'ounces' => 'oz',
        'lb' => 'lb',
        'lbs' => 'lb',
        'pound' => 'lb',
        'pounds' => 'lb',
    ];

    public static function lookup(string $word): ?string
    {
        return self::ALIASES[strtolower($word)] ?? null;
    }

    public static function family(string $unit): string
    {
        return match ($unit) {
            'tsp', 'tbsp', 'cup', 'ml', 'l' => 'volume',
            'g', 'kg', 'oz', 'lb' => 'mass',
            default => throw new InvalidArgumentException('Unknown unit: ' . $unit),
        };
    }

    public static function factor(string $unit): int
    {
        return match ($unit) {
            'tsp' => 5,
            'tbsp' => 15,
            'cup' => 240,
            'ml' => 1,
            'l' => 1000,
            'g' => 1,
            'kg' => 1000,
            'oz' => 28,
            'lb' => 448,
            default => throw new InvalidArgumentException('Unknown unit: ' . $unit),
        };
    }
}
