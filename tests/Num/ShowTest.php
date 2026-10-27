<?php

declare(strict_types=1);

namespace Ladlemath\Tests\Num;

use Ladlemath\Num\Fraction;
use Ladlemath\Num\Show;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ShowTest extends TestCase
{
    /**
     * @return list<array{0: string, 1: string, 2: int|null, 3: int|null}>
     */
    public static function glyphValueCases(): array
    {
        return [
            ['quarter', '¼', 1, 4],
            ['half', '½', 1, 2],
            ['three quarters', '¾', 3, 4],
            ['third', '⅓', 1, 3],
            ['two thirds', '⅔', 2, 3],
            ['eighth', '⅛', 1, 8],
            ['three eighths', '⅜', 3, 8],
            ['five eighths', '⅝', 5, 8],
            ['seven eighths', '⅞', 7, 8],
            ['unrecognized text', 'x', null, null],
        ];
    }

    #[Test]
    #[DataProvider('glyphValueCases')]
    public function testGlyphValues(
        string $case,
        string $glyph,
        ?int $expectedNum,
        ?int $expectedDen,
    ): void {
        self::assertSame(39, self::totalCases(), $case);
        $actual = Show::glyphValue($glyph);

        if ($expectedNum === null) {
            self::assertNull($actual, $case);

            return;
        }

        self::assertNotNull($actual, $case);
        self::assertSame($expectedNum, $actual->num, $case);
        self::assertSame($expectedDen, $actual->den, $case);
    }

    /**
     * @return list<array{0: string, 1: string, 2: int, 3: int, 4: bool}>
     */
    public static function mixedCases(): array
    {
        return [
            ['zero', '0', 0, 1, false],
            ['one', '1', 1, 1, false],
            ['integer', '5', 5, 1, false],
            ['integer in glyph mode', '5', 5, 1, true],
            ['fraction', '1/2', 1, 2, false],
            ['half glyph', '½', 1, 2, true],
            ['third', '1/3', 1, 3, false],
            ['third glyph', '⅓', 1, 3, true],
            ['three quarters', '3/4', 3, 4, false],
            ['three quarters glyph', '¾', 3, 4, true],
            ['mixed number', '1 1/2', 3, 2, false],
            ['unknown fractional glyph', '1 1/7', 8, 7, true],
            ['quarter glyph', '¼', 1, 4, true],
            ['two-thirds glyph', '⅔', 2, 3, true],
            ['eighth glyph', '⅛', 1, 8, true],
            ['three-eighths glyph', '⅜', 3, 8, true],
            ['negative mixed glyph', '-1½', -3, 2, true],
        ];
    }

    #[Test]
    #[DataProvider('mixedCases')]
    public function testMixedFormatting(
        string $case,
        string $expected,
        int $num,
        int $den,
        bool $glyphs,
    ): void {
        self::assertSame($expected, Show::mixed(Fraction::of($num, $den), $glyphs), $case);
    }

    /**
     * @return list<array{0: string, 1: string, 2: int, 3: int}>
     */
    public static function hundredthsCases(): array
    {
        return [
            ['decimal', '1.5', 3, 2],
            ['leading zero', '0.05', 1, 20],
            ['integer without decimal point', '5', 5, 1],
            ['negative hundredths', '-0.05', -1, 20],
        ];
    }

    #[Test]
    #[DataProvider('hundredthsCases')]
    public function testHundredthsFormatting(
        string $case,
        string $expected,
        int $num,
        int $den,
    ): void {
        self::assertSame($expected, Show::hundredths(Fraction::of($num, $den)), $case);
    }

    /**
     * @return list<array{0: string, 1: string, 2: int, 3: int, 4: string|null, 5: bool}>
     */
    public static function valueCases(): array
    {
        return [
            ['cup', '1 1/2', 3, 2, 'cup', false],
            ['cup with glyphs', '1½', 3, 2, 'cup', true],
            ['bare count', '1 1/2', 3, 2, null, false],
            ['milliliters use whole number', '250', 250, 1, 'ml', false],
            ['kilograms use hundredths', '1.5', 3, 2, 'kg', false],
            ['liters retain exact hundredths in glyph mode', '0.05', 1, 20, 'l', true],
            ['grams use whole number', '1000', 1000, 1, 'g', false],
            ['teaspoons use glyphs', '⅝', 5, 8, 'tsp', true],
        ];
    }

    #[Test]
    #[DataProvider('valueCases')]
    public function testValueFormatting(
        string $case,
        string $expected,
        int $num,
        int $den,
        ?string $unit,
        bool $glyphs,
    ): void {
        self::assertSame(
            $expected,
            Show::value(Fraction::of($num, $den), $unit, $glyphs),
            $case,
        );
    }

    private static function totalCases(): int
    {
        return count(self::glyphValueCases())
            + count(self::mixedCases())
            + count(self::hundredthsCases())
            + count(self::valueCases());
    }
}
