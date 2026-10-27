<?php

declare(strict_types=1);

namespace Ladlemath\Tests\Kitchen;

use Ladlemath\Kitchen\Rounding;
use Ladlemath\Num\Amount;
use Ladlemath\Num\Fraction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RoundingTest extends TestCase
{
    #[Test]
    #[DataProvider('stepCases')]
    public function stepUsesTheValueThresholds(
        ?string $unit,
        int $valueNum,
        int $valueDen,
        int $expectedNum,
        int $expectedDen,
    ): void {
        $actual = Rounding::step($unit, Fraction::of($valueNum, $valueDen));

        self::assertFraction($actual, $expectedNum, $expectedDen);
    }

    /**
     * @return list<array{0: ?string, 1: int, 2: int, 3: int, 4: int}>
     */
    public static function stepCases(): array
    {
        return [
            ['tsp', 1, 2, 1, 8],
            ['tsp', 1, 1, 1, 4],
            ['tbsp', 5, 1, 1, 4],
            ['tbsp', 6, 1, 1, 2],
            ['cup', 1, 2, 1, 8],
            ['cup', 1, 1, 1, 4],
            ['cup', 7, 2, 1, 4],
            ['cup', 4, 1, 1, 2],
            [null, 1, 2, 1, 4],
            [null, 3, 1, 1, 2],
            [null, 10, 1, 1, 1],
            ['g', 10, 1, 5, 1],
            ['ml', 100, 1, 10, 1],
            ['kg', 1, 1, 1, 20],
            ['l', 1, 1, 1, 20],
            ['oz', 4, 1, 1, 2],
            ['lb', 1, 1, 1, 4],
        ];
    }

    #[Test]
    #[DataProvider('roundCases')]
    public function roundsToTheNearestStepWithUpwardTiesAndPositiveMinimum(
        ?string $unit,
        int $valueNum,
        int $valueDen,
        int $expectedNum,
        int $expectedDen,
    ): void {
        $actual = Rounding::round($unit, Fraction::of($valueNum, $valueDen));

        self::assertFraction($actual, $expectedNum, $expectedDen);
    }

    /**
     * @return list<array{0: ?string, 1: int, 2: int, 3: int, 4: int}>
     */
    public static function roundCases(): array
    {
        return [
            ['tsp', 1, 3, 3, 8],
            ['tsp', 1, 16, 1, 8],
            ['tsp', 1, 20, 1, 8],
            ['tsp', 5, 2, 5, 2],
            ['tbsp', 17, 3, 23, 4],
            ['cup', 1, 3, 3, 8],
            ['cup', 10, 3, 13, 4],
            ['cup', 9, 2, 9, 2],
            [null, 1, 10, 1, 4],
            [null, 3, 2, 3, 2],
            [null, 7, 3, 5, 2],
            [null, 21, 2, 11, 1],
            ['g', 5, 2, 3, 1],
            ['g', 7, 2, 4, 1],
            ['tsp', 0, 1, 0, 1],
            [null, 999999999, 1, 999999999, 1],
        ];
    }

    #[Test]
    #[DataProvider('promotionCases')]
    public function promotesRepeatedlyFromTheLowEndpoint(
        ?string $unit,
        int $lowNum,
        int $lowDen,
        ?int $highNum,
        ?int $highDen,
        ?string $expectedUnit,
        int $expectedLowNum,
        int $expectedLowDen,
        ?int $expectedHighNum,
        ?int $expectedHighDen,
        string $expectedDescription,
    ): void {
        $amount = new Amount(
            $unit,
            Fraction::of($lowNum, $lowDen),
            self::fractionFromPair($highNum, $highDen),
        );
        $actual = Rounding::promote($amount);

        self::assertSame($expectedUnit, $actual->unit);
        self::assertFraction($actual->lo, $expectedLowNum, $expectedLowDen);
        self::assertFractionPair($actual->hi, $expectedHighNum, $expectedHighDen);
        self::assertSame($expectedDescription, self::describe($actual));
    }

    /**
     * @return list<array{
     *     0: ?string,
     *     1: int,
     *     2: int,
     *     3: ?int,
     *     4: ?int,
     *     5: ?string,
     *     6: int,
     *     7: int,
     *     8: ?int,
     *     9: ?int,
     *     10: string
     * }>
     */
    public static function promotionCases(): array
    {
        return [
            ['tsp', 3, 1, null, null, 'tbsp', 1, 1, null, null, 'tbsp,1'],
            ['tsp', 2, 1, null, null, 'tsp', 2, 1, null, null, 'tsp,2'],
            ['tsp', 12, 1, 15, 1, 'cup', 1, 4, 5, 16, 'cup,1/4 to 5/16'],
            ['tbsp', 4, 1, null, null, 'cup', 1, 4, null, null, 'cup,1/4'],
            ['tbsp', 7, 2, 5, 1, 'tbsp', 7, 2, 5, 1, 'tbsp,7/2 to 5'],
            ['g', 1500, 1, null, null, 'kg', 3, 2, null, null, 'kg,3/2'],
            ['g', 999, 1, 1200, 1, 'g', 999, 1, 1200, 1, 'g,999 to 1200'],
            ['ml', 1000, 1, null, null, 'l', 1, 1, null, null, 'l,1'],
            ['oz', 16, 1, null, null, 'lb', 1, 1, null, null, 'lb,1'],
        ];
    }

    private static function describe(Amount $amount): string
    {
        $unit = $amount->unit ?? 'null';
        $description = $unit . ',' . self::fractionText($amount->lo);

        if ($amount->hi !== null) {
            $description .= ' to ' . self::fractionText($amount->hi);
        }

        return $description;
    }

    private static function fractionText(Fraction $fraction): string
    {
        return $fraction->den === 1
            ? (string) $fraction->num
            : $fraction->num . '/' . $fraction->den;
    }

    private static function assertFraction(Fraction $actual, int $num, int $den): void
    {
        self::assertSame($num, $actual->num);
        self::assertSame($den, $actual->den);
    }

    private static function assertFractionPair(?Fraction $actual, ?int $num, ?int $den): void
    {
        if ($num === null || $den === null) {
            self::assertNull($actual);
            self::assertSame($num, $den);

            return;
        }

        self::assertNotNull($actual);
        self::assertFraction($actual, $num, $den);
    }

    private static function fractionFromPair(?int $num, ?int $den): ?Fraction
    {
        if ($num === null || $den === null) {
            self::assertSame($num, $den);

            return null;
        }

        return Fraction::of($num, $den);
    }
}
