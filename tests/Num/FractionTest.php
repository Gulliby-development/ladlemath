<?php

declare(strict_types=1);

namespace Ladlemath\Tests\Num;

use InvalidArgumentException;
use Ladlemath\Base\Failure;
use Ladlemath\Num\Fraction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FractionTest extends TestCase
{
    /**
     * @return list<array{0: string, 1: int, 2: int, 3: int|null, 4: int|null, 5: ''|'failure'|'invalid'}>
     */
    public static function ofCases(): array
    {
        return [
            ['reduces terms', 2, 4, 1, 2, ''],
            ['zero normalizes', 0, 5, 0, 1, ''],
            ['negative numerator', -3, 6, -1, 2, ''],
            ['negative denominator', 3, -6, -1, 2, ''],
            ['integer', 6, 3, 2, 1, ''],
            ['two negative terms', -5, -10, 1, 2, ''],
            ['below limit', 999999999, 1, 999999999, 1, ''],
            ['reduce before limit validation', 1000000000, 2, 500000000, 1, ''],
            ['exclusive denominator limit', 1, 1000000000, null, null, 'failure'],
            ['zero denominator', 1, 0, null, null, 'invalid'],
        ];
    }

    #[Test]
    #[DataProvider('ofCases')]
    public function testOfNormalizesAndValidates(
        string $case,
        int $num,
        int $den,
        ?int $expectedNum,
        ?int $expectedDen,
        string $expectedException,
    ): void {
        self::assertSame(
            27,
            count(self::ofCases()) + count(self::arithmeticCases()) + count(self::comparisonCases()),
        );

        if ($expectedException === 'failure') {
            try {
                Fraction::of($num, $den);
                self::fail($case . ' should exceed the fraction limit');
            } catch (Failure $failure) {
                self::assertSame(2, $failure->exitCode);
                self::assertSame('quantity too large', $failure->getMessage());
            }

            return;
        }

        if ($expectedException === 'invalid') {
            $this->expectException(InvalidArgumentException::class);
            Fraction::of($num, $den);

            return;
        }

        $actual = Fraction::of($num, $den);
        self::assertSame('', $expectedException, $case);
        self::assertNotNull($expectedNum, $case);
        self::assertNotNull($expectedDen, $case);
        self::assertSame($expectedNum, $actual->num, $case);
        self::assertSame($expectedDen, $actual->den, $case);
        self::assertSame($expectedNum === 0, $actual->isZero(), $case);
        self::assertSame($expectedDen === 1, $actual->isInteger(), $case);
    }

    /**
     * @return list<array{0: string, 1: string, 2: int, 3: int, 4: int, 5: int, 6: int|null, 7: int|null, 8: ''|'failure'|'invalid'}>
     */
    public static function arithmeticCases(): array
    {
        return [
            ['1/2 + 1/3', 'add', 1, 2, 1, 3, 5, 6, ''],
            ['1/2 * 1/3', 'mul', 1, 2, 1, 3, 1, 6, ''],
            ['1/2 / 1/3', 'div', 1, 2, 1, 3, 3, 2, ''],
            ['5 + 7/8', 'add', 5, 1, 7, 8, 47, 8, ''],
            ['2/3 + 3/4', 'add', 2, 3, 3, 4, 17, 12, ''],
            ['0 + 1/3', 'add', 0, 1, 1, 3, 1, 3, ''],
            ['999999999 * 2', 'mul', 999999999, 1, 2, 1, null, null, 'failure'],
            ['999999999 + 1', 'add', 999999999, 1, 1, 1, null, null, 'failure'],
            ['999999999 / (1/2)', 'div', 999999999, 1, 1, 2, null, null, 'failure'],
            ['2 * 999999999', 'mul', 2, 1, 999999999, 1, null, null, 'failure'],
            ['999999998/999999999 * 999999999/999999998', 'mul', 999999998, 999999999, 999999999, 999999998, 1, 1, ''],
            ['1/2 / 0', 'div', 1, 2, 0, 1, null, null, 'invalid'],
        ];
    }

    #[Test]
    #[DataProvider('arithmeticCases')]
    public function testArithmeticIsExactAndChecksReducedResults(
        string $case,
        string $operation,
        int $leftNum,
        int $leftDen,
        int $rightNum,
        int $rightDen,
        ?int $expectedNum,
        ?int $expectedDen,
        string $expectedException,
    ): void {
        $left = Fraction::of($leftNum, $leftDen);
        $right = Fraction::of($rightNum, $rightDen);

        try {
            $actual = match ($operation) {
                'add' => $left->add($right),
                'mul' => $left->mul($right),
                'div' => $left->div($right),
                default => throw new InvalidArgumentException('unknown test operation'),
            };
        } catch (Failure $failure) {
            self::assertSame('failure', $expectedException, $case);
            self::assertSame(2, $failure->exitCode, $case);
            self::assertSame('quantity too large', $failure->getMessage(), $case);

            return;
        } catch (InvalidArgumentException $exception) {
            self::assertSame('invalid', $expectedException, $case);
            self::assertNotSame('', $exception->getMessage(), $case);

            return;
        }

        self::assertSame('', $expectedException, $case);
        self::assertNotNull($expectedNum, $case);
        self::assertNotNull($expectedDen, $case);
        self::assertSame($expectedNum, $actual->num, $case);
        self::assertSame($expectedDen, $actual->den, $case);
    }

    /**
     * @return list<array{0: string}>
     */
    public static function comparisonCases(): array
    {
        return [
            ['positive floor'],
            ['negative fractional floor'],
            ['negative mixed floor'],
            ['greater, reverse, and signed less comparisons'],
            ['equal comparison'],
        ];
    }

    #[Test]
    #[DataProvider('comparisonCases')]
    public function testFloorComparisonAndPredicates(string $case): void
    {
        switch ($case) {
            case 'positive floor':
                self::assertSame(3, Fraction::of(7, 2)->floor());

                return;
            case 'negative fractional floor':
                self::assertSame(-1, Fraction::of(-1, 2)->floor());

                return;
            case 'negative mixed floor':
                self::assertSame(-4, Fraction::of(-7, 2)->floor());

                return;
            case 'greater, reverse, and signed less comparisons':
                $half = Fraction::of(1, 2);
                $third = Fraction::of(1, 3);
                $negativeHalf = Fraction::of(-1, 2);
                $zero = Fraction::of(0);

                self::assertSame(1, $half->compare($third));
                self::assertSame(-1, $third->compare($half));
                self::assertTrue($third->lt($half));
                self::assertTrue($half->ge($third));
                self::assertSame(-1, $negativeHalf->compare($zero));
                self::assertTrue($negativeHalf->lt($zero));
                self::assertTrue($zero->ge($negativeHalf));

                return;
            case 'equal comparison':
                $reducedHalf = Fraction::of(2, 4);
                $half = Fraction::of(1, 2);

                self::assertSame(0, $reducedHalf->compare($half));
                self::assertFalse($reducedHalf->lt($half));
                self::assertTrue($reducedHalf->ge($half));

                return;
        }

        self::fail('Unknown comparison case: ' . $case);
    }
}
