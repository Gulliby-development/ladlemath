<?php

declare(strict_types=1);

namespace Ladlemath\Tests\Recipe;

use Ladlemath\Base\Failure;
use Ladlemath\Base\Inputs;
use Ladlemath\Base\Text;
use Ladlemath\Model\Problem;
use Ladlemath\Recipe\Document;
use Ladlemath\Recipe\Entry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DocumentTest extends TestCase
{
    /**
     * @param list<array{0: int, 1: string}> $entries
     * @param list<string> $problems
     */
    #[Test]
    #[DataProvider('suppliedDocuments')]
    public function itLoadsTheSuppliedDocumentExamples(
        string $text,
        string $title,
        ?int $serves,
        array $entries,
        array $problems,
    ): void {
        $this->assertDocumentMatches($text, $title, $serves, $entries, $problems);
    }

    /**
     * @return list<array{0: string, 1: string, 2: ?int, 3: list<array{0: int, 1: string}>, 4: list<string>}>
     */
    public static function suppliedDocuments(): array
    {
        return [
            ['', '', null, [], []],
            [
                "Title: Pancakes\nServes: 4\n2 cups flour\n",
                'Pancakes',
                4,
                [[3, 'flour']],
                [],
            ],
            ["\u{FEFF}Title: T\n1 egg\n", 'T', null, [[2, 'egg']], []],
            ["2 cups flour\r\n1 egg\r\n", '', null, [[1, 'flour'], [2, 'egg']], []],
            ["# c\n\n  # d\n  \n1 egg", '', null, [[5, 'egg']], []],
            [
                "Serves: 4\nServes: 5\n",
                '',
                4,
                [],
                ['recipe.txt:2:1: LM005 serves is already set'],
            ],
            [
                "Serves: x\nServes: 0\nServes: 12345\nServes:\n",
                '',
                null,
                [],
                [
                    'recipe.txt:1:8: LM004 invalid serves value "x"',
                    'recipe.txt:2:8: LM004 invalid serves value "0"',
                    'recipe.txt:3:8: LM004 invalid serves value "12345"',
                    'recipe.txt:4:8: LM004 invalid serves value ""',
                ],
            ],
        ];
    }

    /**
     * @param list<array{0: int, 1: string}> $entries
     * @param list<string> $problems
     */
    #[Test]
    #[DataProvider('metadataDocuments')]
    public function itAppliesMetadataOnlyAtColumnOneAndKeepsTheSpecifiedValues(
        string $text,
        string $title,
        ?int $serves,
        array $entries,
        array $problems,
    ): void {
        $this->assertDocumentMatches($text, $title, $serves, $entries, $problems);
    }

    /**
     * @return list<array{0: string, 1: string, 2: ?int, 3: list<array{0: int, 1: string}>, 4: list<string>}>
     */
    public static function metadataDocuments(): array
    {
        return [
            ["Title: A\nTitle: B\nTitle: \t\n", '', null, [], []],
            [
                "Serves: x\nServes: 0004\nServes: 0\nServes: 5",
                '',
                4,
                [],
                [
                    'recipe.txt:1:8: LM004 invalid serves value "x"',
                    'recipe.txt:3:8: LM004 invalid serves value "0"',
                    'recipe.txt:4:1: LM005 serves is already set',
                ],
            ],
            [
                " Title: T\ntitle: T\n serves: 2",
                '',
                null,
                [[1, 'Title: T'], [2, 'title: T'], [3, 'serves: 2']],
                [],
            ],
        ];
    }

    #[Test]
    public function itReportsEveryStructuralProblemInOrderAndRetainsValidContent(): void
    {
        $line200 = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaacafé';
        $line201 = '#aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaacafé';
        $zeroWithControls = "0\x01\x7Faaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa";
        $text = implode("\n", [
            "#x\x01\x7F",
            "Title: Café\x01",
            $line200,
            $line201,
            $zeroWithControls,
            "2 cups flour\x01",
            "#\tpermitted tab",
            "\r",
        ]);

        self::assertSame(200, Text::length($line200));
        self::assertSame(201, Text::length($line201));
        self::assertSame(202, Text::length($zeroWithControls));

        $document = Document::load($text);

        self::assertSame("Café\x01", $document->title);
        self::assertSame(
            [
                [3, $line200],
                [6, "flour\x01"],
                [8, "\r"],
            ],
            $this->entrySummaries($document->entries),
        );
        self::assertSame([$line200, "2 cups flour\x01", "\r"], [
            $document->entries[0]->text,
            $document->entries[1]->text,
            $document->entries[2]->text,
        ]);
        self::assertSame('cup', $document->entries[1]->amount?->unit);
        self::assertSame(2, $document->entries[1]->amount->lo->num);
        self::assertSame(1, $document->entries[1]->amount->lo->den);
        self::assertSame([
            'recipe.txt:1:3: LM008 control character U+0001',
            'recipe.txt:1:4: LM008 control character U+007F',
            'recipe.txt:2:12: LM008 control character U+0001',
            'recipe.txt:4:201: LM007 line has 201 characters, the limit is 200',
            'recipe.txt:5:2: LM008 control character U+0001',
            'recipe.txt:5:3: LM008 control character U+007F',
            'recipe.txt:5:201: LM007 line has 202 characters, the limit is 200',
            'recipe.txt:5:1: LM006 quantity is zero',
            'recipe.txt:6:13: LM008 control character U+0001',
            'recipe.txt:8:1: LM008 control character U+000D',
        ], $this->problemTexts($document->problems));
    }

    #[Test]
    public function itStrictlyLoadsAValidInjectedFile(): void
    {
        $inputs = new Inputs(
            static fn (string $path): ?string => $path === 'soup.txt'
                ? "Title: Soup\nServes: 2\n1 cup broth"
                : null,
            static fn (): string => '',
        );

        $document = Document::strict('soup.txt', $inputs);

        self::assertSame('Soup', $document->title);
        self::assertSame(2, $document->serves);
        self::assertSame([[3, 'broth']], $this->entrySummaries($document->entries));
        self::assertSame([], $document->problems);
    }

    #[Test]
    #[DataProvider('strictFailures')]
    public function itRejectsTheFirstDocumentOrInputFailure(
        string $path,
        ?string $fileText,
        string $stdin,
        string $expectedMessage,
    ): void {
        $inputs = new Inputs(
            static fn (string $requestedPath): ?string => $requestedPath === $path ? $fileText : null,
            static fn (): string => $stdin,
        );

        try {
            Document::strict($path, $inputs);
        } catch (Failure $failure) {
            self::assertSame(2, $failure->exitCode);
            self::assertSame($expectedMessage, $failure->getMessage());

            return;
        }

        self::fail('Expected strict loading to throw a Failure.');
    }

    /**
     * @return list<array{0: string, 1: ?string, 2: string, 3: string}>
     */
    public static function strictFailures(): array
    {
        return [
            [
                'a.txt',
                "0 cups flour\n2 cups",
                '',
                'a.txt:1:1: LM006 quantity is zero',
            ],
            [
                '-',
                'unused file content',
                '0 cups flour',
                '<stdin>:1:1: LM006 quantity is zero',
            ],
            [
                'missing.txt',
                null,
                '',
                'cannot read missing.txt',
            ],
            [
                '-',
                null,
                "\xFF",
                '<stdin>: input is not valid UTF-8',
            ],
        ];
    }

    /**
     * @param list<array{0: int, 1: string}> $expectedEntries
     * @param list<string> $expectedProblems
     */
    private function assertDocumentMatches(
        string $text,
        string $title,
        ?int $serves,
        array $expectedEntries,
        array $expectedProblems,
    ): void {
        $document = Document::load($text);

        self::assertSame($title, $document->title);
        self::assertSame($serves, $document->serves);
        self::assertSame($expectedEntries, $this->entrySummaries($document->entries));
        self::assertSame($expectedProblems, $this->problemTexts($document->problems));
    }

    /**
     * @param list<Entry> $entries
     * @return list<array{0: int, 1: string}>
     */
    private function entrySummaries(array $entries): array
    {
        $summaries = [];
        foreach ($entries as $entry) {
            $summaries[] = [$entry->line, $entry->name];
        }

        return $summaries;
    }

    /**
     * @param list<Problem> $problems
     * @return list<string>
     */
    private function problemTexts(array $problems): array
    {
        $texts = [];
        foreach ($problems as $problem) {
            $texts[] = $problem->text('recipe.txt');
        }

        return $texts;
    }
}
