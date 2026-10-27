<?php

declare(strict_types=1);

namespace Ladlemath\Tests\Base;

use InvalidArgumentException;
use Ladlemath\Base\Text;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TextTest extends TestCase
{
    #[Test]
    public function testCpsSplitsUtf8CodePoints(): void
    {
        self::assertSame(['é', '½'], Text::cps('é½'));
    }

    #[Test]
    public function testLengthCountsUtf8CodePoints(): void
    {
        self::assertSame(2, Text::length('é½'));
    }

    #[Test]
    public function testCpsReturnsEmptyListForEmptyText(): void
    {
        self::assertSame([], Text::cps(''));
    }

    #[Test]
    public function testIsUtf8RejectsInvalidByte(): void
    {
        self::assertFalse(Text::isUtf8("\xFF"));
    }

    #[Test]
    public function testIsUtf8AcceptsUtf8Text(): void
    {
        self::assertTrue(Text::isUtf8('é'));
    }

    #[Test]
    public function testCpsRejectsInvalidUtf8(): void
    {
        self::expectException(InvalidArgumentException::class);

        Text::cps("\xFF");
    }

    #[Test]
    public function testLengthRejectsInvalidUtf8(): void
    {
        self::expectException(InvalidArgumentException::class);

        Text::length("\xFF");
    }

    #[Test]
    public function testIsBlankAcceptsSpace(): void
    {
        self::assertTrue(Text::isBlank(' '));
    }

    #[Test]
    public function testIsBlankAcceptsTab(): void
    {
        self::assertTrue(Text::isBlank("\t"));
    }

    #[Test]
    public function testIsBlankRejectsNull(): void
    {
        self::assertFalse(Text::isBlank(null));
    }

    #[Test]
    public function testIsBlankRejectsNewline(): void
    {
        self::assertFalse(Text::isBlank("\n"));
    }

    #[Test]
    public function testIsBlankRejectsNonbreakingSpace(): void
    {
        self::assertFalse(Text::isBlank(' '));
    }

    #[Test]
    public function testIsDigitAcceptsZero(): void
    {
        self::assertTrue(Text::isDigit('0'));
    }

    #[Test]
    public function testIsDigitAcceptsNine(): void
    {
        self::assertTrue(Text::isDigit('9'));
    }

    #[Test]
    public function testIsDigitRejectsNull(): void
    {
        self::assertFalse(Text::isDigit(null));
    }

    #[Test]
    public function testIsDigitRejectsSuperscriptTwo(): void
    {
        self::assertFalse(Text::isDigit('²'));
    }

    #[Test]
    public function testIsDigitRejectsMultipleCharacters(): void
    {
        self::assertFalse(Text::isDigit('12'));
    }

    #[Test]
    public function testTrimBlanksRemovesOnlyEdgeSpacesAndTabs(): void
    {
        self::assertSame('é', Text::trimBlanks(" \té \t"));
    }

    #[Test]
    public function testTrimBlanksPreservesNewlines(): void
    {
        self::assertSame("\né\n", Text::trimBlanks("\né\n"));
    }

    #[Test]
    public function testNameKeyNormalizesAsciiAndBlankRuns(): void
    {
        self::assertSame('salt É leaf', Text::nameKey(" \tSALT  É\tLeaf "));
    }

    #[Test]
    public function testLinesReturnsEmptyStringForEmptyList(): void
    {
        self::assertSame('', Text::lines([]));
    }

    #[Test]
    public function testLinesJoinsAndTerminatesLinesWithLf(): void
    {
        self::assertSame("a\nb\n", Text::lines(['a', 'b']));
    }

    #[Test]
    public function testPluralUsesSingularForOne(): void
    {
        self::assertSame('1 file', Text::plural(1, 'file'));
    }

    #[Test]
    public function testPluralUsesPluralForZero(): void
    {
        self::assertSame('0 files', Text::plural(0, 'file'));
    }

    #[Test]
    public function testPluralUsesPluralForTwo(): void
    {
        self::assertSame('2 files', Text::plural(2, 'file'));
    }

    #[Test]
    public function testShownQuotesText(): void
    {
        self::assertSame('"x"', Text::shown('x'));
    }

    #[Test]
    public function testShownEscapesBackslashQuoteAndLineControls(): void
    {
        $expected = '"' . '\\\\' . '\\"' . '\\t' . '\\r' . '\\n' . '"';

        self::assertSame($expected, Text::shown("\\\"\t\r\n"));
    }

    #[Test]
    public function testLengthReturnsZeroForEmptyText(): void
    {
        self::assertSame(0, Text::length(''));
    }

    #[Test]
    public function testNameKeyReturnsEmptyStringForEmptyText(): void
    {
        self::assertSame('', Text::nameKey(''));

        $testCount = 0;
        $reflection = new \ReflectionClass(self::class);

        foreach ($reflection->getMethods() as $method) {
            if ($method->getAttributes(Test::class) !== []) {
                $testCount++;
            }
        }

        self::assertSame(29, $testCount);
    }
}
