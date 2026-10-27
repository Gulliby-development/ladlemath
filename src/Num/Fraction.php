<?php

declare(strict_types=1);

namespace Ladlemath\Num;

use InvalidArgumentException;
use Ladlemath\Base\Failure;

final class Fraction
{
    public const LIMIT = 1000000000;

    private function __construct(
        public readonly int $num,
        public readonly int $den,
    ) {
    }

    public static function of(int $num, int $den = 1): self
    {
        if ($den === 0) {
            throw new InvalidArgumentException('denominator must not be zero');
        }

        if ($num === 0) {
            return new self(0, 1);
        }

        if ($num === PHP_INT_MIN && $den === PHP_INT_MIN) {
            return new self(1, 1);
        }

        $negative = ($num < 0) !== ($den < 0);
        $gcd = self::gcd($num, $den);
        $reducedNum = intdiv($num, $gcd);
        $reducedDen = intdiv($den, $gcd);

        if ($reducedDen < 0) {
            if ($reducedDen <= -self::LIMIT) {
                self::tooLarge();
            }

            $reducedDen = -$reducedDen;
        } elseif ($reducedDen >= self::LIMIT) {
            self::tooLarge();
        }

        if ($reducedNum < 0) {
            if ($reducedNum <= -self::LIMIT) {
                self::tooLarge();
            }

            $magnitude = -$reducedNum;
        } else {
            if ($reducedNum >= self::LIMIT) {
                self::tooLarge();
            }

            $magnitude = $reducedNum;
        }

        $normalizedNum = $negative ? -$magnitude : $magnitude;

        return new self($normalizedNum, $reducedDen);
    }

    public function add(self $other): self
    {
        $common = self::gcd($this->den, $other->den);
        $leftScale = intdiv($other->den, $common);
        $rightScale = intdiv($this->den, $common);
        $leftNum = self::multiplyIntegers($this->num, $leftScale);
        $rightNum = self::multiplyIntegers($other->num, $rightScale);
        $num = self::addIntegers($leftNum, $rightNum);
        $den = self::multiplyIntegers($this->den, $leftScale);

        return self::of($num, $den);
    }

    public function mul(self $other): self
    {
        $leftCancel = self::gcd($this->num, $other->den);
        $rightCancel = self::gcd($other->num, $this->den);
        $num = self::multiplyIntegers(
            intdiv($this->num, $leftCancel),
            intdiv($other->num, $rightCancel),
        );
        $den = self::multiplyIntegers(
            intdiv($this->den, $rightCancel),
            intdiv($other->den, $leftCancel),
        );

        return self::of($num, $den);
    }

    public function div(self $other): self
    {
        if ($other->num === 0) {
            throw new InvalidArgumentException('divisor must not be zero');
        }

        $numeratorCancel = self::gcd($this->num, $other->num);
        $denominatorCancel = self::gcd($other->den, $this->den);
        $num = self::multiplyIntegers(
            intdiv($this->num, $numeratorCancel),
            intdiv($other->den, $denominatorCancel),
        );
        $den = self::multiplyIntegers(
            intdiv($this->den, $denominatorCancel),
            intdiv($other->num, $numeratorCancel),
        );

        return self::of($num, $den);
    }

    public function compare(self $other): int
    {
        $thisNegative = $this->num < 0;
        $otherNegative = $other->num < 0;

        if ($thisNegative !== $otherNegative) {
            return $thisNegative ? -1 : 1;
        }

        $leftNum = $thisNegative ? -$this->num : $this->num;
        $rightNum = $otherNegative ? -$other->num : $other->num;
        $comparison = self::comparePositiveFractions(
            $leftNum,
            $this->den,
            $rightNum,
            $other->den,
        );

        return $thisNegative ? -$comparison : $comparison;
    }

    public function lt(self $other): bool
    {
        return $this->compare($other) < 0;
    }

    public function ge(self $other): bool
    {
        return $this->compare($other) >= 0;
    }

    public function isZero(): bool
    {
        return $this->num === 0;
    }

    public function isInteger(): bool
    {
        return $this->den === 1;
    }

    public function floor(): int
    {
        $whole = intdiv($this->num, $this->den);

        if ($this->num < 0 && $this->num % $this->den !== 0) {
            return $whole - 1;
        }

        return $whole;
    }

    private static function gcd(int $left, int $right): int
    {
        if ($left === PHP_INT_MIN) {
            $a = $left;
            $b = $right < 0 ? -$right : $right;
        } elseif ($right === PHP_INT_MIN) {
            $a = $right;
            $b = $left < 0 ? -$left : $left;
        } else {
            $a = $left < 0 ? -$left : $left;
            $b = $right < 0 ? -$right : $right;
        }

        while ($b !== 0) {
            $remainder = $a % $b;
            $a = $b;
            $b = $remainder < 0 ? -$remainder : $remainder;
        }

        return $a;
    }

    private static function comparePositiveFractions(
        int $leftNum,
        int $leftDen,
        int $rightNum,
        int $rightDen,
    ): int {
        $reverse = false;

        while (true) {
            $leftWhole = intdiv($leftNum, $leftDen);
            $rightWhole = intdiv($rightNum, $rightDen);

            if ($leftWhole !== $rightWhole) {
                $comparison = $leftWhole <=> $rightWhole;

                return $reverse ? -$comparison : $comparison;
            }

            $leftRemainder = $leftNum % $leftDen;
            $rightRemainder = $rightNum % $rightDen;

            if ($leftRemainder === 0 || $rightRemainder === 0) {
                if ($leftRemainder === 0 && $rightRemainder === 0) {
                    return 0;
                }

                $comparison = $leftRemainder === 0 ? -1 : 1;

                return $reverse ? -$comparison : $comparison;
            }

            $leftNum = $leftDen;
            $leftDen = $leftRemainder;
            $rightNum = $rightDen;
            $rightDen = $rightRemainder;
            $reverse = !$reverse;
        }
    }

    private static function multiplyIntegers(int $left, int $right): int
    {
        if ($left === 0 || $right === 0) {
            return 0;
        }

        if ($left > 0 && $right > 0 && $left > intdiv(PHP_INT_MAX, $right)) {
            self::tooLarge();
        }

        if ($left > 0 && $right < 0 && $right < intdiv(PHP_INT_MIN, $left)) {
            self::tooLarge();
        }

        if ($left < 0 && $right > 0 && $left < intdiv(PHP_INT_MIN, $right)) {
            self::tooLarge();
        }

        if ($left < 0 && $right < 0 && $left < intdiv(PHP_INT_MAX, $right)) {
            self::tooLarge();
        }

        return $left * $right;
    }

    private static function addIntegers(int $left, int $right): int
    {
        if ($right > 0 && $left > PHP_INT_MAX - $right) {
            self::tooLarge();
        }

        if ($right < 0 && $left < PHP_INT_MIN - $right) {
            self::tooLarge();
        }

        return $left + $right;
    }

    private static function tooLarge(): never
    {
        throw new Failure(2, 'quantity too large');
    }
}
