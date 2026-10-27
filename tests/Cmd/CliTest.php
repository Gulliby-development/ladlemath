<?php

declare(strict_types=1);

namespace Ladlemath\Tests\Cmd;

use Ladlemath\Base\Inputs;
use Ladlemath\Base\Output;
use Ladlemath\Cmd\Cli;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CliTest extends TestCase
{
    /**
     * @return list<array{0: list<string>, 1: int, 2: string, 3: string}>
     */
    public static function literalCases(): array
    {
        return [
            [[], 2, '', Cli::USAGE],
            [['--help', 'nonsense'], 0, Cli::USAGE, ''],
            [['-h'], 0, Cli::USAGE, ''],
            [['wat'], 2, '', "ladlemath: unknown command wat\n"],
            [['scale', '--x=1', '--serves=0'], 2, '', "ladlemath: unknown option --x\n"],
            [['merge', '--glyphs=1'], 2, '', "ladlemath: option --glyphs takes no value\n"],
            [['scale', '--serves'], 2, '', "ladlemath: option --serves needs a value\n"],
            [['scale', '--serves='], 2, '', "ladlemath: option --serves needs a non-empty value\n"],
            [['scale', '--serves', '2', '--factor', '3'], 2, '', "ladlemath: scale takes either --serves or --factor\n"],
            [['scale'], 2, '', "ladlemath: scale needs --serves or --factor\n"],
            [['scale', '--serves', '0', 'missing.txt'], 2, '', "ladlemath: invalid --serves value: 0\n"],
            [['scale', '--factor', '1 1/2'], 2, '', "ladlemath: invalid --factor value: 1 1/2\n"],
            [['convert', '--to', 'imperial'], 2, '', "ladlemath: invalid --to value: imperial\n"],
            [['parse'], 2, '', "ladlemath: parse needs exactly one file\n"],
            [['check', 'missing.txt'], 2, '', "ladlemath: cannot read missing.txt\n"],
            [['scale', '--serves', '2', 'a.txt'], 2, '', "ladlemath: a.txt: no Serves line\n"],
        ];
    }

    /**
     * @param list<string> $arguments
     */
    #[Test]
    #[DataProvider('literalCases')]
    public function routesCommandsAndReportsBoundaryResults(
        array $arguments,
        int $expectedExitCode,
        string $expectedStdout,
        string $expectedStderr,
    ): void {
        self::assertCount(16, self::literalCases());
        self::assertSame(
            "usage: ladlemath scale (--serves N | --factor Q) [--promote] [--glyphs] FILE\n"
                . "       ladlemath convert --to metric|us [--glyphs] FILE\n"
                . "       ladlemath merge [--glyphs] FILE...\n"
                . "       ladlemath check FILE...\n"
                . "       ladlemath parse FILE\n",
            Cli::USAGE,
        );

        $files = [
            'a.txt' => "1 egg\n",
            'missing.txt' => null,
        ];
        $inputs = new Inputs(
            static function (string $path) use ($files): ?string {
                return $files[$path] ?? null;
            },
            static fn (): string => '',
        );
        $output = new Output();

        $exitCode = Cli::run($arguments, $inputs, $output);

        self::assertSame($expectedExitCode, $exitCode);
        self::assertSame($expectedStdout, $output->stdout());
        self::assertSame($expectedStderr, $output->stderr());

        if (str_starts_with($expectedStderr, 'ladlemath: ')) {
            $message = substr($expectedStderr, strlen('ladlemath: '), -1);
            self::assertSame(
                str_replace('MESSAGE', $message, "ladlemath: MESSAGE\n"),
                $output->stderr(),
            );
        }
    }
}
