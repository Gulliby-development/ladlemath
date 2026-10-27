<?php

declare(strict_types=1);

namespace Ladlemath\Tests\Cmd;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ProcessTest extends TestCase
{
    #[Test]
    public function launcherRejectsInvalidUtf8FromStandardInput(): void
    {
        $launcher = realpath(
            dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'ladlemath.php',
        );
        if ($launcher === false) {
            throw new RuntimeException('Unable to resolve the launcher path.');
        }

        $pipes = [];
        $process = proc_open(
            [PHP_BINARY, $launcher, 'check', '-'],
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
        );
        if (!is_resource($process)) {
            throw new RuntimeException('Unable to start the launcher process.');
        }

        $stdinPipe = $pipes[0] ?? null;
        $stdoutPipe = $pipes[1] ?? null;
        $stderrPipe = $pipes[2] ?? null;
        $standardOutput = '';
        $standardError = '';
        $exitCode = null;

        try {
            if (!is_resource($stdinPipe) || !is_resource($stdoutPipe) || !is_resource($stderrPipe)) {
                throw new RuntimeException('Unable to open launcher process pipes.');
            }

            $input = "2 cups flour\n\xFF\n";
            if (fwrite($stdinPipe, $input) !== strlen($input)) {
                throw new RuntimeException('Unable to write all input to the launcher process.');
            }
            if (!fclose($stdinPipe)) {
                throw new RuntimeException('Unable to close launcher process stdin.');
            }
            $stdinPipe = null;

            $standardOutput = stream_get_contents($stdoutPipe);
            if ($standardOutput === false) {
                throw new RuntimeException('Unable to read launcher process stdout.');
            }
            $standardError = stream_get_contents($stderrPipe);
            if ($standardError === false) {
                throw new RuntimeException('Unable to read launcher process stderr.');
            }

            if (!fclose($stdoutPipe)) {
                throw new RuntimeException('Unable to close launcher process stdout.');
            }
            $stdoutPipe = null;
            if (!fclose($stderrPipe)) {
                throw new RuntimeException('Unable to close launcher process stderr.');
            }
            $stderrPipe = null;
        } finally {
            if (is_resource($stdinPipe)) {
                fclose($stdinPipe);
            }
            if (is_resource($stdoutPipe)) {
                fclose($stdoutPipe);
            }
            if (is_resource($stderrPipe)) {
                fclose($stderrPipe);
            }
            $exitCode = proc_close($process);
        }

        self::assertSame('', $standardOutput);
        self::assertSame("ladlemath: <stdin>: input is not valid UTF-8\n", $standardError);
        self::assertSame(2, $exitCode);
    }
}
