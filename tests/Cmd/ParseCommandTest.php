<?php

declare(strict_types=1);

namespace Ladlemath\Tests\Cmd;

use Closure;
use Ladlemath\Base\Failure;
use Ladlemath\Base\Inputs;
use Ladlemath\Base\Options;
use Ladlemath\Base\Output;
use Ladlemath\Cmd\ParseCommand;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ParseCommandTest extends TestCase
{
    #[Test]
    public function rejectsMissingAndExtraOperandsBeforeReading(): void
    {
        $reads = [];
        $out = new Output();
        $inputs = $this->memoryInputs([], '', $reads);

        $this->assertFailure(
            static fn (): int => ParseCommand::run(Options::parse([], []), $inputs, $out),
            'parse needs exactly one file',
        );
        $this->assertFailure(
            static fn (): int => ParseCommand::run(Options::parse(['a.txt', 'b.txt'], []), $inputs, $out),
            'parse needs exactly one file',
        );

        self::assertSame([], $reads);
        self::assertSame('', $out->stdout());
        self::assertSame('', $out->stderr());
    }

    #[Test]
    public function strictlyLoadedInputFailuresPreserveExistingBuffers(): void
    {
        $reads = [];
        $out = new Output();
        $out->writeOut("existing stdout\n");
        $out->writeErr("existing stderr\n");

        $badInputs = $this->memoryInputs(
            ['bad.txt' => "0 cups flour\n1/0 tsp z\n"],
            '',
            $reads,
        );
        $this->assertFailure(
            static fn (): int => ParseCommand::run(Options::parse(['bad.txt'], []), $badInputs, $out),
            'bad.txt:1:1: LM006 quantity is zero',
        );
        self::assertSame(['bad.txt'], $reads);
        self::assertSame("existing stdout\n", $out->stdout());
        self::assertSame("existing stderr\n", $out->stderr());

        $reads = [];
        $missingInputs = $this->memoryInputs(['missing.txt' => null], '', $reads);
        $this->assertFailure(
            static fn (): int => ParseCommand::run(Options::parse(['missing.txt'], []), $missingInputs, $out),
            'cannot read missing.txt',
        );
        self::assertSame(['missing.txt'], $reads);
        self::assertSame("existing stdout\n", $out->stdout());
        self::assertSame("existing stderr\n", $out->stderr());

        $reads = [];
        $invalidInputs = $this->memoryInputs([], "\xFF", $reads);
        $this->assertFailure(
            static fn (): int => ParseCommand::run(Options::parse(['-'], []), $invalidInputs, $out),
            '<stdin>: input is not valid UTF-8',
        );
        self::assertSame(['-'], $reads);
        self::assertSame("existing stdout\n", $out->stdout());
        self::assertSame("existing stderr\n", $out->stderr());
    }

    #[Test]
    public function printsTheWorkedPancakesTableExactly(): void
    {
        $recipe = "Title: Pancakes\nServes: 4\n\n1 1/2 cups all-purpose flour\n2 tbsp sugar\n½ tsp salt\n2-3 tbsp butter, melted\n1 large egg\n250 ml milk\nsalt to taste\n";
        $reads = [];
        $out = new Output();
        $inputs = $this->memoryInputs(['a.txt' => $recipe], '', $reads);

        self::assertSame(0, ParseCommand::run(Options::parse(['a.txt'], []), $inputs, $out));
        self::assertSame(
            "LINE  QTY     UNIT  NAME               NOTE\n"
                . "4     1 1/2   cup   all-purpose flour  -\n"
                . "5     2       tbsp  sugar              -\n"
                . "6     1/2     tsp   salt               -\n"
                . "7     2 to 3  tbsp  butter             melted\n"
                . "8     1       -     large egg          -\n"
                . "9     250     ml    milk               -\n"
                . "10    -       -     salt to taste      -\n",
            $out->stdout(),
        );
        self::assertSame('', $out->stderr());
        self::assertSame(['a.txt'], $reads);
    }

    #[Test]
    public function printsOnlyTheHeadingWhenThereAreNoIngredientEntries(): void
    {
        $reads = [];
        $out = new Output();
        $inputs = $this->memoryInputs(['empty.txt' => ''], '', $reads);

        self::assertSame(0, ParseCommand::run(Options::parse(['empty.txt'], []), $inputs, $out));
        self::assertSame("LINE  QTY  UNIT  NAME  NOTE\n", $out->stdout());
        self::assertSame('', $out->stderr());
        self::assertSame(['empty.txt'], $reads);
    }

    #[Test]
    public function usesCodePointWidthsAndKeepsMetricQuantitiesExact(): void
    {
        $reads = [];
        $out = new Output();
        $inputs = $this->memoryInputs(
            ['unicode.txt' => "1.5 kg café, mûr\nsalt\n"],
            '',
            $reads,
        );

        self::assertSame(0, ParseCommand::run(Options::parse(['unicode.txt'], []), $inputs, $out));
        self::assertSame(
            "LINE  QTY    UNIT  NAME  NOTE\n"
                . "1     1 1/2  kg    café  mûr\n"
                . "2     -      -     salt  -\n",
            $out->stdout(),
        );
        self::assertSame('', $out->stderr());
        self::assertSame(['unicode.txt'], $reads);
    }

    #[Test]
    public function formatsBothRangeEndpointsAndPadsLongNameAndNote(): void
    {
        $reads = [];
        $out = new Output();
        $inputs = $this->memoryInputs(
            ['range.txt' => "1/2 to 3/2 cups a very long ingredient name, freshly chopped\n"],
            '',
            $reads,
        );

        self::assertSame(0, ParseCommand::run(Options::parse(['range.txt'], []), $inputs, $out));
        self::assertSame(
            "LINE  QTY           UNIT  NAME                         NOTE\n"
                . "1     1/2 to 1 1/2  cup   a very long ingredient name  freshly chopped\n",
            $out->stdout(),
        );
        self::assertSame('', $out->stderr());
        self::assertSame(['range.txt'], $reads);
    }

    /**
     * @param array<string, ?string> $files
     * @param list<string> $reads
     */
    private function memoryInputs(array $files, string $stdin, array &$reads): Inputs
    {
        return new Inputs(
            static function (string $path) use ($files, &$reads): ?string {
                $reads[] = $path;

                return $files[$path] ?? null;
            },
            static function () use ($stdin, &$reads): string {
                $reads[] = '-';

                return $stdin;
            },
        );
    }

    /**
     * @param Closure(): int $operation
     */
    private function assertFailure(Closure $operation, string $message): void
    {
        try {
            $operation();
            self::fail('Expected a Failure.');
        } catch (Failure $failure) {
            self::assertSame(Failure::class, $failure::class);
            self::assertSame(2, $failure->exitCode);
            self::assertSame($message, $failure->getMessage());
        }
    }
}
