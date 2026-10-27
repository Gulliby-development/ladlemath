<?php

declare(strict_types=1);

namespace Ladlemath\Tests\Kitchen;

use Ladlemath\Kitchen\Quantity;
use Ladlemath\Num\Amount;
use Ladlemath\Num\Fraction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class QuantityTest extends TestCase
{
    #[Test]
    #[DataProvider('finishCases')]
    public function finishRoundsEachEndpointAndRenders(
        ?string $unit,
        Fraction $lo,
        ?Fraction $hi,
        bool $glyphs,
        string $expected,
    ): void {
        self::assertSame($expected, Quantity::finish(new Amount($unit, $lo, $hi), $glyphs));
    }

    /**
     * @return list<array{0: ?string, 1: Fraction, 2: ?Fraction, 3: bool, 4: string}>
     */
    public static function finishCases(): array
    {
        return [
            ['cup', Fraction::of(5, 4), null, false, '1 1/4 cups'],
            ['cup', Fraction::of(1), null, false, '1 cup'],
            ['cup', Fraction::of(1, 2), Fraction::of(3, 2), false, '1/2 to 1 1/2 cups'],
            ['cup', Fraction::of(1, 3), null, true, '⅜ cup'],
            ['tsp', Fraction::of(7, 8), Fraction::of(5, 4), true, '⅞ to 1¼ tsp'],
            ['tbsp', Fraction::of(17, 3), null, false, '5 3/4 tbsp'],
            [null, Fraction::of(5, 2), null, false, '2 1/2'],
            [null, Fraction::of(1, 10), Fraction::of(9, 10), true, '¼ to 1'],
            ['g', Fraction::of(123), Fraction::of(127), false, '120 to 130 g'],
            ['ml', Fraction::of(375), null, false, '380 ml'],
            ['cup', Fraction::of(9, 10), Fraction::of(1), false, '7/8 to 1 cup'],
        ];
    }

    #[Test]
    #[DataProvider('renderCases')]
    public function renderKeepsValuesUnchangedAndFormatsRanges(
        ?string $unit,
        Fraction $lo,
        ?Fraction $hi,
        bool $glyphs,
        string $expected,
    ): void {
        self::assertSame($expected, Quantity::render(new Amount($unit, $lo, $hi), $glyphs));
    }

    /**
     * @return list<array{0: ?string, 1: Fraction, 2: ?Fraction, 3: bool, 4: string}>
     */
    public static function renderCases(): array
    {
        return [
            ['cup', Fraction::of(1, 3), null, false, '1/3 cup'],
            ['cup', Fraction::of(1, 2), Fraction::of(3, 2), true, '½ to 1½ cups'],
            ['cup', Fraction::of(1), Fraction::of(1), false, '1 cup'],
            ['cup', Fraction::of(3, 2), Fraction::of(3, 2), false, '1 1/2 cups'],
            [null, Fraction::of(5, 2), null, false, '2 1/2'],
            ['kg', Fraction::of(3, 2), null, false, '1.5 kg'],
            ['l', Fraction::of(1, 20), null, true, '0.05 l'],
            ['tsp', Fraction::of(5, 8), null, true, '⅝ tsp'],
        ];
    }
}
