<?php

declare(strict_types=1);

namespace Ladlemath\Tests\Kitchen;

use Ladlemath\Kitchen\Conversion;
use Ladlemath\Num\Amount;
use Ladlemath\Num\Fraction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ConversionTest extends TestCase
{
    /**
     * @param ?string $unit
     * @param ?Fraction $hi
     * @param ?string $expectedUnit
     * @param ?Fraction $expectedLo
     * @param ?Fraction $expectedHi
     */
    #[Test]
    #[DataProvider('conversionCases')]
    public function testConvertsExactly(
        ?string $unit,
        Fraction $lo,
        ?Fraction $hi,
        string $to,
        ?string $expectedUnit,
        ?Fraction $expectedLo,
        ?Fraction $expectedHi,
    ): void {
        $result = Conversion::convert(new Amount($unit, $lo, $hi), $to);

        if ($expectedUnit === null) {
            self::assertNull($result);

            return;
        }

        if ($result === null) {
            self::fail('Expected a converted amount.');
        }

        if ($expectedLo === null) {
            self::fail('Expected a low endpoint.');
        }

        self::assertSame($expectedUnit, $result->unit);
        self::assertSame($expectedLo->num, $result->lo->num);
        self::assertSame($expectedLo->den, $result->lo->den);

        if ($expectedHi === null) {
            self::assertNull($result->hi);

            return;
        }

        if ($result->hi === null) {
            self::fail('Expected a high endpoint.');
        }

        self::assertSame($expectedHi->num, $result->hi->num);
        self::assertSame($expectedHi->den, $result->hi->den);
    }

    /**
     * @return list<array{0: ?string, 1: Fraction, 2: ?Fraction, 3: string, 4: ?string, 5: ?Fraction, 6: ?Fraction}>
     */
    public static function conversionCases(): array
    {
        return [
            ['tsp', Fraction::of(1, 2), null, 'metric', 'ml', Fraction::of(5, 2), null],
            ['tbsp', Fraction::of(2), Fraction::of(3), 'metric', 'ml', Fraction::of(30), Fraction::of(45)],
            ['cup', Fraction::of(3, 2), null, 'metric', 'ml', Fraction::of(360), null],
            ['cup', Fraction::of(5), null, 'metric', 'l', Fraction::of(6, 5), null],
            ['oz', Fraction::of(4), null, 'metric', 'g', Fraction::of(112), null],
            ['lb', Fraction::of(2), null, 'metric', 'g', Fraction::of(896), null],
            ['ml', Fraction::of(250), null, 'metric', null, null, null],
            [null, Fraction::of(2), null, 'metric', null, null, null],
            ['tsp', Fraction::of(2), null, 'us', null, null, null],
            ['ml', Fraction::of(5), null, 'us', 'tsp', Fraction::of(1), null],
            ['ml', Fraction::of(7), null, 'us', 'tsp', Fraction::of(7, 5), null],
            ['ml', Fraction::of(15), null, 'us', 'tbsp', Fraction::of(1), null],
            ['ml', Fraction::of(60), Fraction::of(120), 'us', 'cup', Fraction::of(1, 4), Fraction::of(1, 2)],
            ['g', Fraction::of(448), Fraction::of(896), 'us', 'lb', Fraction::of(1), Fraction::of(2)],
            ['kg', Fraction::of(1, 4), null, 'us', 'oz', Fraction::of(125, 14), null],
            ['l', Fraction::of(1), null, 'us', 'cup', Fraction::of(25, 6), null],
        ];
    }
}
