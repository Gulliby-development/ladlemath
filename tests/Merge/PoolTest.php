<?php

declare(strict_types=1);

namespace Ladlemath\Tests\Merge;

use LogicException;
use Ladlemath\Merge\Pool;
use Ladlemath\Recipe\Document;
use Ladlemath\Recipe\Entry;
use Ladlemath\Recipe\Line;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PoolTest extends TestCase
{
    /**
     * @param list<string> $entries
     * @param list<string> $expected
     */
    #[Test]
    #[DataProvider('mergeCases')]
    public function combinesLiteralMergeExamples(array $entries, array $expected): void
    {
        $pool = new Pool();

        foreach ($entries as $entry) {
            $pool->add(self::parsedEntry($entry));
        }

        self::assertSame($expected, $pool->lines(false));
    }

    /**
     * @return list<array{0: list<string>, 1: list<string>}>
     */
    public static function mergeCases(): array
    {
        return [
            [['1 cup flour', '2 cups flour'], ['3 cups flour']],
            [['1 cup flour', '100 g flour'], ['1 cup flour', '100 g flour']],
            [
                ['2 tbsp butter', '1 tsp butter', '1/4 cup butter'],
                ['3/8 cup butter'],
            ],
            [['Salt', 'salt', '1 tsp salt'], ['1 tsp salt', 'Salt']],
            [['2 eggs', '3 eggs', '1 egg'], ['1 egg', '5 eggs']],
        ];
    }

    #[Test]
    public function doublesThePancakesRecipeMergeExample(): void
    {
        $document = Document::load(
            "Title: Pancakes\n"
            . "Serves: 4\n"
            . "\n"
            . "1 1/2 cups all-purpose flour\n"
            . "2 tbsp sugar\n"
            . "1/2 tsp salt\n"
            . "2-3 tbsp butter, melted\n"
            . "1 large egg\n"
            . "250 ml milk\n"
            . "salt to taste\n",
        );
        self::assertSame([], $document->problems);

        $pool = new Pool();
        foreach ($document->entries as $entry) {
            $pool->add($entry);
            $pool->add($entry);
        }

        self::assertSame(
            [
                '3 cups all-purpose flour',
                '1/4 to 3/8 cup butter',
                '2 large egg',
                '500 ml milk',
                '1 tsp salt',
                'salt to taste',
                '1/4 cup sugar',
            ],
            $pool->lines(false),
        );
    }

    #[Test]
    public function emptyPoolHasNoLinesInEitherGlyphMode(): void
    {
        $pool = new Pool();

        self::assertSame([], $pool->lines(false));
        self::assertSame([], $pool->lines(true));
        self::assertSame([], $pool->lines(false));
    }

    #[Test]
    public function combinesNormalizedNamesByFamilyAndKeepsExactTotalsAcrossRendering(): void
    {
        $pool = new Pool();
        foreach ([
            '1/4 to 1/2 cup Brown  Sugar, packed',
            "1 tbsp brown\tsugar, melted",
            '1 oz brown sugar, sifted',
            '28 g BROWN   SUGAR, packed',
            '2-3 Brown sugar, large',
            "1 brown\tsugar, chopped",
            'Brown Sugar',
            "brown\tsugar",
        ] as $line) {
            $pool->add(self::parsedEntry($line));
        }

        $plain = [
            '3/8 to 5/8 cup Brown  Sugar',
            '2 oz brown sugar',
            '3 to 4 Brown sugar',
            'Brown Sugar',
        ];
        $glyphs = [
            '⅜ to ⅝ cup Brown  Sugar',
            '2 oz brown sugar',
            '3 to 4 Brown sugar',
            'Brown Sugar',
        ];

        self::assertSame($plain, $pool->lines(false));
        self::assertSame($glyphs, $pool->lines(true));
        self::assertSame($plain, $pool->lines(false));

        $pool->add(self::parsedEntry('1/16 cup brown sugar, fine'));
        self::assertSame($plain, $pool->lines(false));
        self::assertSame($glyphs, $pool->lines(true));
    }

    private static function parsedEntry(string $line): Entry
    {
        $entry = Line::parse($line, 1);
        if (!$entry instanceof Entry) {
            throw new LogicException('Expected a valid ingredient line: ' . $line);
        }

        return $entry;
    }
}
