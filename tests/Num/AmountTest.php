<?php

declare(strict_types=1);

namespace Ladlemath\Tests\Num;

use InvalidArgumentException;
use Ladlemath\Num\Amount;
use Ladlemath\Num\Fraction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AmountTest extends TestCase
{
    /**
     * @return list<array{0: string, 1: 'times'|'dividedBy', 2: ?string, 3: int, 4: int, 5: int|null, 6: int|null, 7: int, 8: int, 9: ?string, 10: int, 11: int, 12: int|null, 13: int|null}>
     */
    public static function amountCases(): array
    {
        return [
            ['single multiplication', 'times', 'cup', 1, 2, null, null, 3, 1, 'cup', 3, 2, null, null],
            ['range multiplication', 'times', 'tsp', 1, 1, 3, 2, 2, 1, 'tsp', 2, 1, 3, 1],
            ['bare count multiplication', 'times', null, 2, 1, null, null, 1, 3, null, 2, 3, null, null],
            ['single unit replacement', 'dividedBy', 'g', 1500, 1, null, null, 1000, 1, 'kg', 3, 2, null, null],
            ['range division and unit replacement', 'dividedBy', 'tsp', 12, 1, 15, 1, 48, 1, 'cup', 1, 4, 5, 16],
            ['range division removes unit', 'dividedBy', 'cup', 1, 1, 2, 1, 2, 1, null, 1, 2, 1, 1],
            ['zero multiplication preserves both endpoints', 'times', null, 0, 1, 2, 1, 0, 1, null, 0, 1, 0, 1],
        ];
    }

    #[Test]
    #[DataProvider('amountCases')]
    public function testTransformsBothEndpointsExactlyWithoutMutatingTheSource(
        string $case,
        string $operation,
        ?string $unit,
        int $loNum,
        int $loDen,
        ?int $hiNum,
        ?int $hiDen,
        int $factorNum,
        int $factorDen,
        ?string $expectedUnit,
        int $expectedLoNum,
        int $expectedLoDen,
        ?int $expectedHiNum,
        ?int $expectedHiDen,
    ): void {
        self::assertSame(7, count(self::amountCases()));

        $lo = Fraction::of($loNum, $loDen);
        $hi = $hiNum === null || $hiDen === null
            ? null
            : Fraction::of($hiNum, $hiDen);
        $amount = new Amount($unit, $lo, $hi);
        $factor = Fraction::of($factorNum, $factorDen);

        $transformed = match ($operation) {
            'times' => $amount->times($factor),
            'dividedBy' => $amount->dividedBy($factor, $expectedUnit),
            default => throw new InvalidArgumentException('Unknown Amount test operation'),
        };

        self::assertNotSame($amount, $transformed, $case);

        self::assertSame($expectedUnit, $transformed->unit, $case);
        self::assertSame($expectedLoNum, $transformed->lo->num, $case);
        self::assertSame($expectedLoDen, $transformed->lo->den, $case);

        if ($expectedHiNum === null || $expectedHiDen === null) {
            self::assertNull($transformed->hi, $case);
        } else {
            self::assertNotNull($transformed->hi, $case);
            self::assertSame($expectedHiNum, $transformed->hi->num, $case);
            self::assertSame($expectedHiDen, $transformed->hi->den, $case);
        }

        self::assertSame($unit, $amount->unit, $case);
        self::assertSame($lo, $amount->lo, $case);
        self::assertSame($hi, $amount->hi, $case);
    }
}
