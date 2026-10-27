<?php

declare(strict_types=1);

namespace Ladlemath\Tests\Num;

use InvalidArgumentException;
use Ladlemath\Base\Failure;
use Ladlemath\Base\Text;
use Ladlemath\Num\Fraction;
use Ladlemath\Num\Scanner;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ScannerTest extends TestCase
{
    /**
     * @param string $name
     * @param string $operation
     * @param string $input
     * @param string $index
     * @param string $expected
     */
    #[Test]
    #[DataProvider('scannerCases')]
    public function scannerMatchesLiteralCases(
        string $name,
        string $operation,
        string $input,
        string $index,
        string $expected,
    ): void {
        if ($operation === 'starts') {
            $character = $input === '' ? null : $input;
            $next = $index === '' ? null : $index;
            $this->assertSame($expected === 'true', Scanner::starts($character, $next));

            return;
        }

        if ($expected === 'InvalidArgumentException') {
            $this->expectException(InvalidArgumentException::class);
            $this->scan($operation, $input, (int) $index);

            return;
        }

        if ($expected === 'Failure') {
            $this->expectException(Failure::class);
            $this->scan($operation, $input, (int) $index);

            return;
        }

        $this->assertSame(
            $expected,
            $this->resultText($this->scan($operation, $input, (int) $index)),
            $name,
        );

        if ($name === 'integer prefix') {
            $this->assertSame(
                '53/1@2',
                $this->resultText($this->scan('number', '53', 0)),
            );
        }
    }

    /**
     * Provides exactly 53 literal scanner cases.
     *
     * @return list<array{0: string, 1: string, 2: string, 3: string, 4: string}>
     */
    public static function scannerCases(): array
    {
        return [
            ['integer one', 'number', '1', '0', '1/1@1'],
            ['integer prefix', 'number', '25', '0', '25/1@2'],
            ['leading zeroes', 'number', '007', '0', '7/1@3'],
            ['simple fraction', 'number', '1/2', '0', '1/2@3'],
            ['fraction three quarters', 'number', '3/4', '0', '3/4@3'],
            ['reduced fraction', 'number', '12/16', '0', '3/4@5'],
            ['mixed number', 'number', '1 1/2', '0', '3/2@5'],
            ['mixed number with two spaces', 'number', '1  1/2', '0', '3/2@6'],
            ['mixed number with hyphen', 'number', '1-1/2', '0', '3/2@5'],
            ['half glyph', 'number', '½', '0', '1/2@1'],
            ['attached half glyph', 'number', '1½', '0', '3/2@2'],
            ['spaced three-quarter glyph', 'number', '2 ¾', '0', '11/4@3'],
            ['glyph prefix', 'number', '⅓x', '0', '1/3@1'],
            ['leading-dot decimal', 'number', '.5', '0', '1/2@2'],
            ['decimal below one', 'number', '0.25', '0', '1/4@4'],
            ['decimal with whole part', 'number', '12.75', '0', '51/4@5'],
            ['four-place decimal', 'number', '3.1415', '0', '6283/2000@6'],
            ['trailing dot is not consumed', 'number', '1.', '0', '1/1@1'],
            ['trailing space is not consumed', 'number', '1 ', '0', '1/1@1'],
            ['non-number prefix', 'number', 'a1', '0', 'null'],
            ['empty input', 'number', '', '0', 'null'],
            ['leading spaces are not skipped', 'number', '   1', '0', 'null'],
            ['zero denominator', 'number', '1/0', '0', 'InvalidArgumentException'],
            ['zero mixed denominator', 'number', '1 3/0', '0', 'InvalidArgumentException'],
            ['integer run too long', 'number', '1234567', '0', 'InvalidArgumentException'],
            ['fraction numerator run too long', 'number', '12345/2', '0', 'InvalidArgumentException'],
            ['decimal run too long', 'number', '1.23456', '0', 'InvalidArgumentException'],
            ['mixed numerator run too long', 'number', '1 12345/2', '0', 'InvalidArgumentException'],
            ['fraction prefix', 'fraction', '12/16x', '0', '3/4@5'],
            ['integer is not a fraction', 'fraction', '12', '0', 'null'],
            ['fraction denominator run too long', 'fraction', '1/00000', '0', 'InvalidArgumentException'],
            ['leading decimal run too long', 'number', '.12345', '0', 'InvalidArgumentException'],
            ['number at a nonzero index', 'number', 'x½', '1', '1/2@2'],
            ['fraction at code-point index', 'fraction', '½1/2', '1', '1/2@4'],
            ['digit starts a number', 'starts', '0', '', 'true'],
            ['glyph starts a number', 'starts', '⅞', '', 'true'],
            ['digit after dot starts a number', 'starts', '.', '5', 'true'],
            ['bare dot does not start a number', 'starts', '.', '', 'false'],
            ['end does not start a number', 'starts', '', '', 'false'],
            ['hyphen does not start a number', 'starts', '-', '1', 'false'],
            ['six-digit integer run', 'number', '999999', '0', '999999/1@6'],
            ['four-digit denominator boundary', 'number', '1/9999', '0', '1/9999@6'],
            ['five-digit denominator is too long', 'number', '1/10000', '0', 'InvalidArgumentException'],
            ['fraction numerator exceeds run limit', 'fraction', '12345/2', '0', 'InvalidArgumentException'],
            ['fraction has zero denominator', 'fraction', '1/0', '0', 'InvalidArgumentException'],
            ['slash without denominator digit', 'fraction', '1/', '0', 'null'],
            ['tab does not separate a mixed number', 'number', "1\t1/2", '0', '1/1@1'],
            ['two spaces do not precede glyph', 'number', '2  ¾', '0', '2/1@1'],
            ['hyphen without fraction is not consumed', 'number', '1-2', '0', '1/1@1'],
            ['four-place leading decimal', 'number', '.0001', '0', '1/10000@5'],
            ['large decimal exceeds Fraction limit', 'number', '999999.9999', '0', 'Failure'],
            ['letter does not start a number', 'starts', 'x', '1', 'false'],
            ['number at arbitrary code-point index', 'number', 'x1/2', '1', '1/2@4'],
        ];
    }

    /**
     * @return array{0: Fraction, 1: int}|null
     */
    private function scan(string $operation, string $input, int $index): ?array
    {
        $cps = Text::cps($input);

        return match ($operation) {
            'number' => Scanner::number($cps, $index),
            'fraction' => Scanner::fraction($cps, $index),
            default => throw new LogicException('Unknown scanner operation.'),
        };
    }

    /**
     * @param array{0: Fraction, 1: int}|null $result
     */
    private function resultText(?array $result): string
    {
        if ($result === null) {
            return 'null';
        }

        return $result[0]->num . '/' . $result[0]->den . '@' . $result[1];
    }
}
