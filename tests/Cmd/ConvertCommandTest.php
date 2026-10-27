<?php

declare(strict_types=1);

namespace Ladlemath\Tests\Cmd;

use Ladlemath\Base\Failure;
use Ladlemath\Base\Inputs;
use Ladlemath\Base\Options;
use Ladlemath\Base\Output;
use Ladlemath\Cmd\ConvertCommand;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ConvertCommandTest extends TestCase
{
    #[Test]
    public function itPrintsTheSuppliedMetricPancakesExample(): void
    {
        $inputs = new Inputs(
            static fn (string $path): ?string => $path === 'a.txt' ? self::pancakesRecipe() : null,
            static fn (): string => '',
        );
        $out = new Output();

        self::assertSame(0, ConvertCommand::run(
            $this->options(['--to', 'metric', 'a.txt']),
            $inputs,
            $out,
        ));
        self::assertSame(
            "Title: Pancakes\nServes: 4\n360 ml all-purpose flour\n30 ml sugar\n3 ml salt\n30 to 45 ml butter, melted\n1 large egg\n250 ml milk\nsalt to taste\n",
            $out->stdout(),
        );
        self::assertSame('', $out->stderr());
    }

    #[Test]
    public function itPrintsTheSuppliedUsGlyphPancakesExample(): void
    {
        $inputs = new Inputs(
            static fn (string $path): ?string => $path === 'a.txt' ? self::pancakesRecipe() : null,
            static fn (): string => '',
        );
        $out = new Output();

        self::assertSame(0, ConvertCommand::run(
            $this->options(['--to=us', '--glyphs', 'a.txt']),
            $inputs,
            $out,
        ));
        self::assertSame(
            "Title: Pancakes\nServes: 4\n1 1/2 cups all-purpose flour\n2 tbsp sugar\n½ tsp salt\n2-3 tbsp butter, melted\n1 large egg\n1 cup milk\nsalt to taste\n",
            $out->stdout(),
        );
        self::assertSame('', $out->stderr());
    }

    #[Test]
    public function itPreservesTrimmedPassthroughEntriesAndFormatsConvertedGlyphs(): void
    {
        $inputs = new Inputs(
            static fn (string $path): ?string => $path === 'recipe.txt'
                ? "  2   eggs  \n  salt to taste  \n  1   CUP sugar  \n  120 ml milk  \n"
                : null,
            static fn (): string => '',
        );
        $out = new Output();

        self::assertSame(0, ConvertCommand::run(
            $this->options(['--to', 'us', '--glyphs', 'recipe.txt']),
            $inputs,
            $out,
        ));
        self::assertSame("2   eggs\nsalt to taste\n1   CUP sugar\n½ cup milk\n", $out->stdout());
        self::assertSame('', $out->stderr());
    }

    #[Test]
    public function itChecksForATargetBeforeCheckingForOperands(): void
    {
        $fileReads = 0;
        $stdinReads = 0;
        $inputs = new Inputs(
            static function (string $path) use (&$fileReads): ?string {
                $fileReads++;

                return null;
            },
            static function () use (&$stdinReads): string {
                $stdinReads++;

                return '';
            },
        );

        $this->assertFailure(
            'convert needs --to',
            $this->options([]),
            $inputs,
        );
        self::assertSame(0, $fileReads);
        self::assertSame(0, $stdinReads);
    }

    #[Test]
    public function itChecksAnInvalidTargetBeforeCheckingExcessOperands(): void
    {
        $fileReads = 0;
        $stdinReads = 0;
        $inputs = new Inputs(
            static function (string $path) use (&$fileReads): ?string {
                $fileReads++;

                return null;
            },
            static function () use (&$stdinReads): string {
                $stdinReads++;

                return '';
            },
        );

        $this->assertFailure(
            'invalid --to value: imperial',
            $this->options(['--to=imperial', 'one.txt', 'two.txt']),
            $inputs,
        );
        self::assertSame(0, $fileReads);
        self::assertSame(0, $stdinReads);
    }

    #[Test]
    public function itRejectsAValidTargetWithoutAnOperandBeforeReadingInput(): void
    {
        $fileReads = 0;
        $stdinReads = 0;
        $inputs = new Inputs(
            static function (string $path) use (&$fileReads): ?string {
                $fileReads++;

                return null;
            },
            static function () use (&$stdinReads): string {
                $stdinReads++;

                return '';
            },
        );

        $this->assertFailure(
            'convert needs exactly one file',
            $this->options(['--to=metric']),
            $inputs,
        );
        self::assertSame(0, $fileReads);
        self::assertSame(0, $stdinReads);
    }

    #[Test]
    public function itReportsAnUnreadableFileWithoutWritingOutput(): void
    {
        $requestedPaths = [];
        $inputs = new Inputs(
            static function (string $path) use (&$requestedPaths): ?string {
                $requestedPaths[] = $path;

                return null;
            },
            static fn (): string => '',
        );

        $this->assertFailure(
            'cannot read missing.txt',
            $this->options(['--to=metric', 'missing.txt']),
            $inputs,
        );
        self::assertSame(['missing.txt'], $requestedPaths);
    }

    #[Test]
    public function itRejectsInvalidUtf8FromInjectedStandardInput(): void
    {
        $stdinReads = 0;
        $inputs = new Inputs(
            static fn (string $path): ?string => null,
            static function () use (&$stdinReads): string {
                $stdinReads++;

                return "2 cups flour\n\xFF\n";
            },
        );

        $this->assertFailure(
            '<stdin>: input is not valid UTF-8',
            $this->options(['--to=us', '-']),
            $inputs,
        );
        self::assertSame(1, $stdinReads);
    }

    #[Test]
    public function itReportsTheFirstProblemWithoutWritingValidPrefixEntries(): void
    {
        $inputs = new Inputs(
            static fn (string $path): ?string => $path === 'recipe.txt'
                ? "Title: Soup\nServes: 2\n1 cup broth\n0 cups salt\n3 to 2 cups flour\n"
                : null,
            static fn (): string => '',
        );

        $this->assertFailure(
            'recipe.txt:4:1: LM006 quantity is zero',
            $this->options(['--to=metric', 'recipe.txt']),
            $inputs,
        );
    }

    /** @param list<string> $args */
    private function options(array $args): Options
    {
        return Options::parse($args, ['to' => 'value', 'glyphs' => 'flag']);
    }

    private static function pancakesRecipe(): string
    {
        return "Title: Pancakes\nServes: 4\n\n1 1/2 cups all-purpose flour\n2 tbsp sugar\n½ tsp salt\n2-3 tbsp butter, melted\n1 large egg\n250 ml milk\nsalt to taste";
    }

    private function assertFailure(string $message, Options $options, Inputs $inputs): void
    {
        $out = new Output();

        try {
            ConvertCommand::run($options, $inputs, $out);
            self::fail('Expected ConvertCommand to throw a Failure.');
        } catch (Failure $failure) {
            self::assertSame(2, $failure->exitCode);
            self::assertSame($message, $failure->getMessage());
        }

        self::assertSame('', $out->stdout());
        self::assertSame('', $out->stderr());
    }
}
