<?php

declare(strict_types=1);

namespace Ladlemath\Tests\Cmd;

use Ladlemath\Base\Failure;
use Ladlemath\Base\Inputs;
use Ladlemath\Base\Options;
use Ladlemath\Base\Output;
use Ladlemath\Cmd\MergeCommand;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MergeSkipTest extends TestCase
{
    private const RECIPE = "Title: Pancakes\n"
        . "Serves: 4\n"
        . "\n"
        . "1 1/2 cups all-purpose flour\n"
        . "2 tbsp sugar\n"
        . "1 tsp salt\n"
        . "200 g Sugar\n"
        . "2 eggs\n"
        . "salt\n";

    /**
     * @param list<string> $arguments
     */
    #[Test]
    #[DataProvider('skipCases')]
    public function leavesTheNamedIngredientsOut(array $arguments, string $expected): void
    {
        $out = new Output();

        $code = MergeCommand::run(
            Options::parse($arguments, ['skip' => 'value', 'glyphs' => 'flag']),
            new Inputs(
                static fn (string $path): ?string => $path === 'a.txt' ? self::RECIPE : null,
                static fn (): string => '',
            ),
            $out,
        );

        self::assertSame(0, $code);
        self::assertSame($expected, $out->stdout());
        self::assertSame('', $out->stderr());
    }

    /**
     * @return array<string, array{list<string>, string}>
     */
    public static function skipCases(): array
    {
        return [
            'nothing skipped' => [
                ['a.txt'],
                "1 1/2 cups all-purpose flour\n2 eggs\n1 tsp salt\nsalt\n2 tbsp sugar\n200 g Sugar\n",
            ],
            'one name, every family' => [
                ['--skip', 'sugar', 'a.txt'],
                "1 1/2 cups all-purpose flour\n2 eggs\n1 tsp salt\nsalt\n",
            ],
            'case is ignored' => [
                ['--skip', 'SALT', 'a.txt'],
                "1 1/2 cups all-purpose flour\n2 eggs\n2 tbsp sugar\n200 g Sugar\n",
            ],
            'two names with blanks around them' => [
                ['--skip= Salt , eggs', 'a.txt'],
                "1 1/2 cups all-purpose flour\n2 tbsp sugar\n200 g Sugar\n",
            ],
            'a name that is not in the recipe' => [
                ['--skip', 'flour', 'a.txt'],
                "1 1/2 cups all-purpose flour\n2 eggs\n1 tsp salt\nsalt\n2 tbsp sugar\n200 g Sugar\n",
            ],
            'everything skipped' => [
                ['--skip', 'all-purpose flour,egg,salt,sugar,eggs', 'a.txt'],
                '',
            ],
        ];
    }

    #[Test]
    public function theLastSkipOptionWins(): void
    {
        $out = new Output();

        $code = MergeCommand::run(
            Options::parse(['--skip', 'salt', '--skip', 'eggs', 'a.txt'], ['skip' => 'value']),
            new Inputs(
                static fn (string $path): ?string => $path === 'a.txt' ? self::RECIPE : null,
                static fn (): string => '',
            ),
            $out,
        );

        self::assertSame(0, $code);
        self::assertSame("1 1/2 cups all-purpose flour\n1 tsp salt\nsalt\n2 tbsp sugar\n200 g Sugar\n", $out->stdout());
    }

    #[Test]
    #[DataProvider('badLists')]
    public function refusesAnEmptyNameInTheList(string $list): void
    {
        $reads = [];
        $inputs = new Inputs(
            static function (string $path) use (&$reads): string {
                $reads[] = $path;

                return self::RECIPE;
            },
            static fn (): string => '',
        );

        try {
            MergeCommand::run(Options::parse(['--skip=' . $list, 'a.txt'], ['skip' => 'value']), $inputs, new Output());
            self::fail('Expected a Failure.');
        } catch (Failure $failure) {
            self::assertSame(2, $failure->exitCode);
            self::assertSame('option --skip needs comma-separated ingredient names', $failure->getMessage());
        }

        self::assertSame([], $reads);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function badLists(): array
    {
        return [
            'blank only' => [' '],
            'trailing comma' => ['salt,'],
            'leading comma' => [',salt'],
            'double comma' => ['salt,,sugar'],
            'blank between commas' => ["salt,\t,sugar"],
        ];
    }

    #[Test]
    public function checksTheFileCountBeforeTheList(): void
    {
        try {
            MergeCommand::run(
                Options::parse(['--skip', ','], ['skip' => 'value']),
                new Inputs(static fn (string $path): string => '', static fn (): string => ''),
                new Output(),
            );
            self::fail('Expected a Failure.');
        } catch (Failure $failure) {
            self::assertSame('merge needs at least one file', $failure->getMessage());
        }
    }
}
