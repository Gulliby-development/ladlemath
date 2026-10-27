<?php

declare(strict_types=1);

namespace Ladlemath\Tests\Unit;

use Ladlemath\Unit\Units;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UnitsTest extends TestCase
{
    /**
     * @param list<string> $aliases
     */
    #[Test]
    #[DataProvider('catalogCases')]
    public function itResolvesEveryCatalogAliasAndProvidesCanonicalMetadata(
        array $aliases,
        string $canonical,
        string $family,
        int $factor,
    ): void {
        foreach ($aliases as $alias) {
            self::assertSame($canonical, Units::lookup($alias));
        }

        self::assertSame($family, Units::family($canonical));
        self::assertSame($factor, Units::factor($canonical));
    }

    /**
     * @return list<array{0: list<string>, 1: string, 2: string, 3: int}>
     */
    public static function catalogCases(): array
    {
        return [
            [['tsp', 'tsps', 'teaspoon', 'teaspoons'], 'tsp', 'volume', 5],
            [['tbsp', 'tbsps', 'tbs', 'tablespoon', 'tablespoons'], 'tbsp', 'volume', 15],
            [['c', 'cup', 'cups'], 'cup', 'volume', 240],
            [['ml', 'milliliter', 'milliliters', 'millilitre', 'millilitres'], 'ml', 'volume', 1],
            [['l', 'liter', 'liters', 'litre', 'litres'], 'l', 'volume', 1000],
            [['g', 'gram', 'grams'], 'g', 'mass', 1],
            [['kg', 'kilogram', 'kilograms'], 'kg', 'mass', 1000],
            [['oz', 'ounce', 'ounces'], 'oz', 'mass', 28],
            [['lb', 'lbs', 'pound', 'pounds'], 'lb', 'mass', 448],
        ];
    }

    #[Test]
    #[DataProvider('lookupExamples')]
    public function itMatchesTheSpecifiedLookupExamples(string $word, string $canonical): void
    {
        self::assertSame($canonical, Units::lookup($word));
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    public static function lookupExamples(): array
    {
        return [
            ['tsp', 'tsp'],
            ['TSP', 'tsp'],
            ['teaspoons', 'tsp'],
            ['tbs', 'tbsp'],
            ['Tablespoon', 'tbsp'],
            ['c', 'cup'],
            ['C', 'cup'],
            ['cups', 'cup'],
            ['mL', 'ml'],
            ['millilitres', 'ml'],
            ['l', 'l'],
            ['Liters', 'l'],
            ['g', 'g'],
            ['grams', 'g'],
        ];
    }

    #[Test]
    #[DataProvider('unknownWords')]
    public function itReturnsNullForWordsOutsideTheCatalog(string $word): void
    {
        self::assertNull(Units::lookup($word));
    }

    /**
     * @return list<array{0: string}>
     */
    public static function unknownWords(): array
    {
        return [
            [''],
            ['pinch'],
            ['tsp.'],
            [' tsp'],
        ];
    }
}
