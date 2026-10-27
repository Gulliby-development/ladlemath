<?php

declare(strict_types=1);

namespace Ladlemath\Tests\Cmd;

use Closure;
use Ladlemath\Base\Failure;
use Ladlemath\Base\Inputs;
use Ladlemath\Base\Options;
use Ladlemath\Base\Output;
use Ladlemath\Cmd\CheckCommand;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CheckCommandTest extends TestCase
{
    #[Test]
    public function rejectsEmptyOperandsBeforeReading(): void
    {
        $reads = [];
        $out = new Output();
        $inputs = $this->memoryInputs([], '', $reads, $out);

        $this->assertFailure(
            static fn (): int => CheckCommand::run(Options::parse([], []), $inputs, $out),
            'check needs at least one file',
        );

        self::assertSame([], $reads);
        self::assertSame('', $out->stdout());
        self::assertSame('', $out->stderr());
    }

    #[Test]
    public function countsIngredientLinesAndFileOccurrencesOnSuccess(): void
    {
        $reads = [];
        $out = new Output();

        self::assertSame(
            0,
            $this->runCheck(['one.txt'], ['one.txt' => "1 egg\n"], '', $out, $reads),
        );
        self::assertSame("ok, 1 ingredient line in 1 file\n", $out->stdout());
        self::assertSame('', $out->stderr());
        self::assertSame(['one.txt'], $reads);

        $reads = [];
        $out = new Output();
        self::assertSame(
            0,
            $this->runCheck(
                ['a.txt', 'b.txt'],
                ['a.txt' => "1 egg\nsalt to taste\n", 'b.txt' => "# empty\n"],
                '',
                $out,
                $reads,
            ),
        );
        self::assertSame("ok, 2 ingredient lines in 2 files\n", $out->stdout());
        self::assertSame('', $out->stderr());
        self::assertSame(['a.txt', 'b.txt'], $reads);

        $reads = [];
        $out = new Output();
        self::assertSame(
            0,
            $this->runCheck(['empty.txt'], ['empty.txt' => ''], '', $out, $reads),
        );
        self::assertSame("ok, 0 ingredient lines in 1 file\n", $out->stdout());
        self::assertSame(['empty.txt'], $reads);

        $reads = [];
        $out = new Output();
        self::assertSame(
            0,
            $this->runCheck(
                ['same.txt', 'same.txt'],
                ['same.txt' => "salt to taste\n"],
                '',
                $out,
                $reads,
            ),
        );
        self::assertSame("ok, 2 ingredient lines in 2 files\n", $out->stdout());
        self::assertSame(['same.txt', 'same.txt'], $reads);
        self::assertSame('', $out->stderr());
    }

    #[Test]
    public function preservesDiagnosticOrderAndPluralizesCounts(): void
    {
        $reads = [];
        $out = new Output();

        self::assertSame(
            1,
            $this->runCheck(['-'], [], "0 cups flour\n", $out, $reads),
        );
        self::assertSame(
            "<stdin>:1:1: LM006 quantity is zero\n1 problem in 1 file\n",
            $out->stdout(),
        );
        self::assertSame('', $out->stderr());
        self::assertSame(['-'], $reads);

        $reads = [];
        $out = new Output();
        self::assertSame(
            1,
            $this->runCheck(
                ['a.txt', 'b.txt', 'c.txt'],
                [
                    'a.txt' => "0 cups flour\n1/0 tsp z\n",
                    'b.txt' => "1 cup flour\n",
                    'c.txt' => "Serves: x\n",
                ],
                '',
                $out,
                $reads,
            ),
        );
        self::assertSame(
            "a.txt:1:1: LM006 quantity is zero\n"
                . "a.txt:2:1: LM001 invalid quantity\n"
                . "c.txt:1:8: LM004 invalid serves value \"x\"\n"
                . "3 problems in 2 files\n",
            $out->stdout(),
        );
        self::assertSame('', $out->stderr());
        self::assertSame(['a.txt', 'b.txt', 'c.txt'], $reads);
    }

    #[Test]
    public function printsTheWorkedPancakesAndBadFileExampleExactly(): void
    {
        $pancakes = "Title: Pancakes\n"
            . "Serves: 4\n"
            . "\n"
            . "1 1/2 cups all-purpose flour\n"
            . "2 tbsp sugar\n"
            . "\u{00BD} tsp salt\n"
            . "2-3 tbsp butter, melted\n"
            . "1 large egg\n"
            . "250 ml milk\n"
            . "salt to taste\n";
        $bad = "0 cups flour\n"
            . "3 to 2 cups x\n"
            . "2 cups\n"
            . "1/0 tsp z\n"
            . "Serves: x\n"
            . "Serves: 3\n"
            . "Serves: 4\n";
        $reads = [];
        $out = new Output();

        self::assertSame(
            1,
            $this->runCheck(['a.txt', 'bad.txt'], ['a.txt' => $pancakes, 'bad.txt' => $bad], '', $out, $reads),
        );
        self::assertSame(
            "bad.txt:1:1: LM006 quantity is zero\n"
                . "bad.txt:2:1: LM002 range is not ascending\n"
                . "bad.txt:3:7: LM003 missing ingredient name\n"
                . "bad.txt:4:1: LM001 invalid quantity\n"
                . "bad.txt:5:8: LM004 invalid serves value \"x\"\n"
                . "bad.txt:7:1: LM005 serves is already set\n"
                . "6 problems in 1 file\n",
            $out->stdout(),
        );
        self::assertSame('', $out->stderr());
        self::assertSame(['a.txt', 'bad.txt'], $reads);
    }

    #[Test]
    public function lateUnreadableInputDoesNotEmitDiagnosticsOrAlterBuffers(): void
    {
        $reads = [];
        $out = new Output();
        $out->writeOut("existing stdout\n");
        $out->writeErr("existing stderr\n");

        $this->assertFailure(
            function () use (&$reads, $out): int {
                return $this->runCheck(
                    ['bad.txt', 'missing.txt'],
                    ['bad.txt' => "0 cups flour\n", 'missing.txt' => null],
                    '',
                    $out,
                    $reads,
                );
            },
            'cannot read missing.txt',
        );

        self::assertSame(['bad.txt', 'missing.txt'], $reads);
        self::assertSame("existing stdout\n", $out->stdout());
        self::assertSame("existing stderr\n", $out->stderr());
    }

    #[Test]
    public function lateInvalidStdinUtf8DoesNotEmitDiagnosticsOrAlterBuffers(): void
    {
        $reads = [];
        $out = new Output();
        $out->writeOut("existing stdout\n");
        $out->writeErr("existing stderr\n");

        $this->assertFailure(
            function () use (&$reads, $out): int {
                return $this->runCheck(
                    ['bad.txt', '-'],
                    ['bad.txt' => "0 cups flour\n"],
                    "2 cups flour\n\xFF\n",
                    $out,
                    $reads,
                );
            },
            '<stdin>: input is not valid UTF-8',
        );

        self::assertSame(['bad.txt', '-'], $reads);
        self::assertSame("existing stdout\n", $out->stdout());
        self::assertSame("existing stderr\n", $out->stderr());
    }

    /**
     * @param list<string> $operands
     * @param array<string, ?string> $files
     * @param list<string> $reads
     */
    private function runCheck(
        array $operands,
        array $files,
        string $stdin,
        Output $out,
        array &$reads,
    ): int {
        $inputs = $this->memoryInputs($files, $stdin, $reads, $out);

        return CheckCommand::run(Options::parse($operands, []), $inputs, $out);
    }

    /**
     * @param array<string, ?string> $files
     * @param list<string> $reads
     */
    private function memoryInputs(array $files, string $stdin, array &$reads, Output $out): Inputs
    {
        $expectedOut = $out->stdout();
        $expectedErr = $out->stderr();

        return new Inputs(
            static function (string $path) use ($files, &$reads, $out, $expectedOut, $expectedErr): ?string {
                self::assertSame($expectedOut, $out->stdout());
                self::assertSame($expectedErr, $out->stderr());
                $reads[] = $path;

                return $files[$path] ?? null;
            },
            static function () use ($stdin, &$reads, $out, $expectedOut, $expectedErr): string {
                self::assertSame($expectedOut, $out->stdout());
                self::assertSame($expectedErr, $out->stderr());
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
