<?php

declare(strict_types=1);

namespace Ladlemath\Tests\Cmd;

use Closure;
use Ladlemath\Base\Failure;
use Ladlemath\Base\Inputs;
use Ladlemath\Base\Options;
use Ladlemath\Base\Output;
use Ladlemath\Cmd\MergeCommand;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MergeCommandTest extends TestCase
{
    #[Test]
    public function mergesTheCompletePancakesRecipeTwice(): void
    {
        $recipe = "Title: Pancakes\n"
            . "Serves: 4\n"
            . "\n"
            . "1 1/2 cups all-purpose flour\n"
            . "2 tbsp sugar\n"
            . "½ tsp salt\n"
            . "2-3 tbsp butter, melted\n"
            . "1 large egg\n"
            . "250 ml milk\n"
            . "salt to taste\n";
        $reads = [];
        $inputs = new Inputs(
            static function (string $path) use (&$reads, $recipe): ?string {
                $reads[] = $path;

                return $path === 'a.txt' ? $recipe : null;
            },
            static function () use (&$reads): string {
                $reads[] = '-';

                return '';
            },
        );
        $out = new Output();

        $code = MergeCommand::run(
            Options::parse(['a.txt', 'a.txt'], ['glyphs' => 'flag']),
            $inputs,
            $out,
        );

        self::assertSame(0, $code);
        self::assertSame(
            "3 cups all-purpose flour\n"
            . "1/4 to 3/8 cup butter\n"
            . "2 large egg\n"
            . "500 ml milk\n"
            . "1 tsp salt\n"
            . "salt to taste\n"
            . "1/4 cup sugar\n",
            $out->stdout(),
        );
        self::assertSame('', $out->stderr());
        self::assertSame(['a.txt', 'a.txt'], $reads);
    }

    #[Test]
    public function mergesOrderedFilesAndStdinAcrossEveryFamilyWithGlyphs(): void
    {
        $files = [
            'first.txt' => "Title: Ignored first title\n"
                . "Serves: 2\n"
                . "1/8 cup Brown  sugar, packed\n"
                . "1 oz Brown  sugar, packed\n"
                . "2 Brown  sugar, large\n"
                . "Brown  sugar\n",
            'last.txt' => "Title: Ignored last title\n1 cup Apple, cored\n",
        ];
        $stdin = "Title: Ignored stdin title\n"
            . "1/8 cup brown sugar, melted\n"
            . "28 g brown sugar\n"
            . "1 brown sugar\n"
            . "brown sugar\n";
        $reads = [];
        $inputs = new Inputs(
            static function (string $path) use (&$reads, $files): ?string {
                $reads[] = $path;

                return $files[$path] ?? null;
            },
            static function () use (&$reads, $stdin): string {
                $reads[] = '-';

                return $stdin;
            },
        );
        $out = new Output();

        $code = MergeCommand::run(
            Options::parse(['first.txt', '-', 'last.txt', '--glyphs'], ['glyphs' => 'flag']),
            $inputs,
            $out,
        );

        self::assertSame(0, $code);
        self::assertSame(
            "1 cup Apple\n"
            . "¼ cup Brown  sugar\n"
            . "2 oz Brown  sugar\n"
            . "3 Brown  sugar\n"
            . "Brown  sugar\n",
            $out->stdout(),
        );
        self::assertSame('', $out->stderr());
        self::assertSame(['first.txt', '-', 'last.txt'], $reads);
    }

    #[Test]
    public function rejectsMissingOperandsBeforeReading(): void
    {
        $reads = [];
        $inputs = new Inputs(
            static function (string $path) use (&$reads): ?string {
                $reads[] = $path;

                return null;
            },
            static function () use (&$reads): string {
                $reads[] = '-';

                return '';
            },
        );
        $out = new Output();

        $this->assertFailure(
            static fn (): int => MergeCommand::run(
                Options::parse([], ['glyphs' => 'flag']),
                $inputs,
                $out,
            ),
            'merge needs at least one file',
        );

        self::assertSame([], $reads);
        self::assertSame('', $out->stdout());
        self::assertSame('', $out->stderr());
    }

    #[Test]
    public function stopsAtAnUnreadableFirstOperand(): void
    {
        $reads = [];
        $inputs = new Inputs(
            static function (string $path) use (&$reads): ?string {
                $reads[] = $path;

                return $path === 'missing.txt' ? null : '1 cup flour';
            },
            static function () use (&$reads): string {
                $reads[] = '-';

                return '';
            },
        );
        $out = new Output();

        $this->assertFailure(
            static fn (): int => MergeCommand::run(
                Options::parse(['missing.txt', 'later.txt'], ['glyphs' => 'flag']),
                $inputs,
                $out,
            ),
            'cannot read missing.txt',
        );

        self::assertSame(['missing.txt'], $reads);
        self::assertSame('', $out->stdout());
        self::assertSame('', $out->stderr());
    }

    #[Test]
    public function stopsAtTheFirstStrictDocumentProblemWithoutPartialOutput(): void
    {
        $files = [
            'first.txt' => "1 cup flour\n",
            'bad.txt' => "0 cups flour\n3 to 2 cups x\n",
            'later.txt' => "1 cup sugar\n",
        ];
        $reads = [];
        $inputs = new Inputs(
            static function (string $path) use (&$reads, $files): ?string {
                $reads[] = $path;

                return $files[$path] ?? null;
            },
            static function () use (&$reads): string {
                $reads[] = '-';

                return '';
            },
        );
        $out = new Output();

        $this->assertFailure(
            static fn (): int => MergeCommand::run(
                Options::parse(['first.txt', 'bad.txt', 'later.txt'], ['glyphs' => 'flag']),
                $inputs,
                $out,
            ),
            'bad.txt:1:1: LM006 quantity is zero',
        );

        self::assertSame(['first.txt', 'bad.txt'], $reads);
        self::assertSame('', $out->stdout());
        self::assertSame('', $out->stderr());
    }

    /**
     * @param Closure(): int $operation
     */
    private function assertFailure(Closure $operation, string $expectedMessage): void
    {
        try {
            $operation();
            self::fail('Expected a Failure.');
        } catch (Failure $failure) {
            self::assertSame(Failure::class, $failure::class);
            self::assertSame(2, $failure->exitCode);
            self::assertSame($expectedMessage, $failure->getMessage());
        }
    }
}
