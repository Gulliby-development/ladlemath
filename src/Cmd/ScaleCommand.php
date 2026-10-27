<?php

declare(strict_types=1);

namespace Ladlemath\Cmd;

use Ladlemath\Base\Failure;
use Ladlemath\Base\Inputs;
use Ladlemath\Base\Options;
use Ladlemath\Base\Output;
use Ladlemath\Base\Text;
use Ladlemath\Kitchen\Quantity;
use Ladlemath\Kitchen\Rounding;
use Ladlemath\Num\Fraction;
use Ladlemath\Recipe\Document;

final class ScaleCommand
{
    public static function run(Options $options, Inputs $inputs, Output $out): int
    {
        $servesValue = $options->value('serves');
        $factorValue = $options->value('factor');

        if ($servesValue !== null && $factorValue !== null) {
            throw new Failure(2, 'scale takes either --serves or --factor');
        }

        if ($servesValue === null && $factorValue === null) {
            throw new Failure(2, 'scale needs --serves or --factor');
        }

        $newServes = $servesValue === null ? null : self::servesValue($servesValue);
        $factor = $factorValue === null ? null : self::factorValue($factorValue);

        if (count($options->operands) !== 1) {
            throw new Failure(2, 'scale needs exactly one file');
        }

        $path = $options->operands[0];
        $document = Document::strict($path, $inputs);

        if ($newServes !== null && $document->serves === null) {
            throw new Failure(2, Inputs::displayName($path) . ': no Serves line');
        }

        if ($newServes !== null) {
            $factor = Fraction::of($newServes, $document->serves ?? 1);
        }

        $lines = [];
        if ($document->title !== '') {
            $lines[] = 'Title: ' . $document->title;
        }

        if ($newServes !== null) {
            $lines[] = 'Serves: ' . $newServes;
        } elseif ($factor !== null && $document->serves !== null) {
            $derivedServes = self::derivedServes($document->serves, $factor);
            if ($derivedServes !== null) {
                $lines[] = 'Serves: ' . $derivedServes;
            }
        }

        foreach ($document->entries as $entry) {
            if ($entry->amount === null) {
                $lines[] = $entry->text;

                continue;
            }

            $scaled = $entry->amount->times($factor ?? Fraction::of(1));
            if ($options->has('promote')) {
                $scaled = Rounding::promote($scaled);
            }

            $lines[] = $entry->withQuantity(Quantity::finish($scaled, $options->has('glyphs')));
        }

        $out->writeOut(Text::lines($lines));

        return 0;
    }

    private static function servesValue(string $value): int
    {
        if (preg_match('/\A[0-9]{1,4}\z/', $value) !== 1) {
            throw new Failure(2, 'invalid --serves value: ' . $value);
        }

        $serves = self::digitsToInt($value);
        if ($serves === 0) {
            throw new Failure(2, 'invalid --serves value: ' . $value);
        }

        return $serves;
    }

    private static function factorValue(string $value): Fraction
    {
        if (preg_match('/\\A[0-9]+\\z/', $value) === 1) {
            $numerator = self::digitsToInt($value);
            if ($numerator === 0) {
                self::invalidFactor($value);
            }

            return Fraction::of($numerator);
        }

        if (preg_match('/\\A[0-9]+\\/[0-9]+\\z/', $value) === 1) {
            [$numeratorText, $denominatorText] = explode('/', $value, 2);
            $numeratorText = self::normalizeDecimalDigits($numeratorText);
            $denominatorText = self::normalizeDecimalDigits($denominatorText);
            if ($numeratorText === '0' || $denominatorText === '0') {
                self::invalidFactor($value);
            }

            return self::reducedFactor($numeratorText, $denominatorText);
        }

        if (preg_match('/\\A[0-9]*\\.[0-9]+\\z/', $value) !== 1) {
            self::invalidFactor($value);
        }

        $point = strpos($value, '.');
        if ($point === false) {
            self::invalidFactor($value);
        }

        $whole = substr($value, 0, $point);
        $fractional = rtrim(substr($value, $point + 1), '0');
        if ($fractional === '') {
            $numerator = self::digitsToInt($whole);
            if ($numerator === 0) {
                self::invalidFactor($value);
            }

            return Fraction::of($numerator);
        }

        $scale = strlen($fractional);
        if ($scale > 29) {
            throw new Failure(2, 'quantity too large');
        }

        $digits = ltrim($whole . $fractional, '0');
        if ($digits === '') {
            self::invalidFactor($value);
        }

        if (strlen($digits) > 30) {
            throw new Failure(2, 'quantity too large');
        }

        $twos = 0;
        while ($twos < $scale && self::lastDigitIsEven($digits)) {
            $digits = self::divideDecimalDigits($digits, 2);
            $twos++;
        }

        $fives = 0;
        while ($fives < $scale && self::lastDigitIsFiveMultiple($digits)) {
            $digits = self::divideDecimalDigits($digits, 5);
            $fives++;
        }

        $denominator = self::boundedPower(2, $scale - $twos);
        $denominatorPart = self::boundedPower(5, $scale - $fives);
        if ($denominator > intdiv(Fraction::LIMIT - 1, $denominatorPart)) {
            throw new Failure(2, 'quantity too large');
        }

        $denominator *= $denominatorPart;

        $numerator = self::digitsToInt($digits);

        return Fraction::of($numerator, $denominator);
    }

    private static function derivedServes(int $serves, Fraction $factor): ?int
    {
        if ($serves % $factor->den !== 0) {
            return null;
        }

        $multiple = intdiv($serves, $factor->den);
        if ($multiple === 0 || $factor->num > intdiv(9999, $multiple)) {
            return null;
        }

        $result = $multiple * $factor->num;

        return $result >= 1 && $result <= 9999 ? $result : null;
    }

    private static function digitsToInt(string $digits): int
    {
        $normalized = ltrim($digits, '0');
        if ($normalized === '') {
            return 0;
        }

        $maximum = (string) PHP_INT_MAX;
        if (
            strlen($normalized) > strlen($maximum)
            || (strlen($normalized) === strlen($maximum) && strcmp($normalized, $maximum) > 0)
        ) {
            throw new Failure(2, 'quantity too large');
        }

        return (int) $normalized;
    }

    private static function lastDigitIsEven(string $digits): bool
    {
        $last = $digits[strlen($digits) - 1];

        return (ord($last) - ord('0')) % 2 === 0;
    }

    private static function lastDigitIsFiveMultiple(string $digits): bool
    {
        $last = $digits[strlen($digits) - 1];

        return $last === '0' || $last === '5';
    }

    private static function divideDecimalDigits(string $digits, int $divisor): string
    {
        $quotient = '';
        $remainder = 0;
        $length = strlen($digits);

        for ($index = 0; $index < $length; $index++) {
            // The remainder is below five, so this intermediate is safely below fifty.
            $current = ($remainder * 10) + (ord($digits[$index]) - ord('0'));
            $quotient .= (string) intdiv($current, $divisor);
            $remainder = $current % $divisor;
        }

        return ltrim($quotient, '0') ?: '0';
    }

    private static function reducedFactor(string $numerator, string $denominator): Fraction
    {
        $divisor = self::decimalGcd($numerator, $denominator);
        $numerator = self::decimalDivideExact($numerator, $divisor);
        $denominator = self::decimalDivideExact($denominator, $divisor);
        $maximum = (string) (Fraction::LIMIT - 1);

        if (
            self::compareDecimalDigits($numerator, $maximum) > 0
            || self::compareDecimalDigits($denominator, $maximum) > 0
        ) {
            throw new Failure(2, 'quantity too large');
        }

        return Fraction::of(self::digitsToInt($numerator), self::digitsToInt($denominator));
    }

    private static function decimalGcd(string $left, string $right): string
    {
        while ($right !== '0') {
            [$left, $right] = [$right, self::decimalRemainder($left, $right)];
        }

        return $left;
    }

    private static function decimalRemainder(string $dividend, string $divisor): string
    {
        $remainder = '0';
        $length = strlen($dividend);

        for ($index = 0; $index < $length; $index++) {
            $remainder = self::normalizeDecimalDigits($remainder . $dividend[$index]);
            while (self::compareDecimalDigits($remainder, $divisor) >= 0) {
                $remainder = self::subtractDecimalDigits($remainder, $divisor);
            }
        }

        return $remainder;
    }

    private static function decimalDivideExact(string $dividend, string $divisor): string
    {
        $quotient = '';
        $remainder = '0';
        $length = strlen($dividend);

        for ($index = 0; $index < $length; $index++) {
            $remainder = self::normalizeDecimalDigits($remainder . $dividend[$index]);
            $digit = 0;
            while (self::compareDecimalDigits($remainder, $divisor) >= 0) {
                $remainder = self::subtractDecimalDigits($remainder, $divisor);
                $digit++;
            }

            $quotient .= (string) $digit;
        }

        return self::normalizeDecimalDigits($quotient);
    }

    private static function subtractDecimalDigits(string $left, string $right): string
    {
        $result = '';
        $borrow = 0;
        $rightOffset = strlen($left) - strlen($right);

        for ($index = strlen($left) - 1; $index >= 0; $index--) {
            $leftDigit = ord($left[$index]) - ord('0') - $borrow;
            $rightIndex = $index - $rightOffset;
            $rightDigit = $rightIndex < 0 ? 0 : ord($right[$rightIndex]) - ord('0');
            if ($leftDigit < $rightDigit) {
                $leftDigit += 10;
                $borrow = 1;
            } else {
                $borrow = 0;
            }

            $result .= chr(ord('0') + $leftDigit - $rightDigit);
        }

        return self::normalizeDecimalDigits(strrev($result));
    }

    private static function compareDecimalDigits(string $left, string $right): int
    {
        if (strlen($left) !== strlen($right)) {
            return strlen($left) <=> strlen($right);
        }

        return strcmp($left, $right) <=> 0;
    }

    private static function normalizeDecimalDigits(string $digits): string
    {
        return ltrim($digits, '0') ?: '0';
    }

    private static function boundedPower(int $base, int $exponent): int
    {
        $value = 1;
        for ($index = 0; $index < $exponent; $index++) {
            if ($value > intdiv(Fraction::LIMIT - 1, $base)) {
                throw new Failure(2, 'quantity too large');
            }

            $value *= $base;
        }

        return $value;
    }

    private static function invalidFactor(string $value): never
    {
        throw new Failure(2, 'invalid --factor value: ' . $value);
    }
}
