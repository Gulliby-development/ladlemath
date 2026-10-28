<?php

declare(strict_types=1);

namespace Ladlemath\Tests\Cmd;

use Ladlemath\Base\Inputs;
use Ladlemath\Base\Output;
use Ladlemath\Cmd\Cli;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class VersionCliTest extends TestCase
{
    /**
     * @return list<array{0: list<string>, 1: ?string, 2: int, 3: string, 4: string}>
     */
    public static function literalCases(): array
    {
        return [
            [['--version'], '{"version": "0.2.0"}', 0, "ladlemath 0.2.0
", ''],
            [['-V'], '{"version": "1.0.0"}', 0, "ladlemath 1.0.0
", ''],
            [['--version', 'scale', 'x'], '{"version": "0.2.0"}', 0, "ladlemath 0.2.0
", ''],
            [['--version'], null, 2, '', "ladlemath: version is not known
"],
            [['--version'], '{"name": "a/b"}', 2, '', "ladlemath: version is not known
"],
            [['-V'], '{"version": "2"}', 2, '', "ladlemath: version is not known
"],
            [['scale', '--version'], '{"version": "0.2.0"}', 2, '', "ladlemath: unknown option --version
"],
            [['--help'], null, 0, Cli::USAGE, ''],
            [['--Version'], '{"version": "0.2.0"}', 2, '', "ladlemath: unknown command --Version
"],
        ];
    }

    /**
     * @param list<string> $arguments
     */
    #[Test]
    #[DataProvider('literalCases')]
    public function printsTheVersionOfTheManifest(
        array $arguments,
        ?string $manifest,
        int $expectedExitCode,
        string $expectedStdout,
        string $expectedStderr,
    ): void {
        self::assertCount(9, self::literalCases());

        $inputs = new Inputs(
            static fn (string $path): ?string => null,
            static fn (): string => '',
        );
        $output = new Output();

        $exitCode = Cli::run($arguments, $inputs, $output, $manifest);

        self::assertSame($expectedExitCode, $exitCode);
        self::assertSame($expectedStdout, $output->stdout());
        self::assertSame($expectedStderr, $output->stderr());
    }

    #[Test]
    public function runWithoutAManifestKeepsTheOldBehaviour(): void
    {
        $inputs = new Inputs(
            static fn (string $path): ?string => null,
            static fn (): string => '',
        );
        $output = new Output();

        self::assertSame(2, Cli::run([], $inputs, $output));
        self::assertSame('', $output->stdout());
        self::assertSame(Cli::USAGE, $output->stderr());
    }
}
