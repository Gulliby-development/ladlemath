<?php

declare(strict_types=1);

namespace Ladlemath\Tests\Cmd;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class VersionProcessTest extends TestCase
{
    #[Test]
    public function launcherPrintsTheVersionOfComposerJson(): void
    {
        $launcher = realpath(
            dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'ladlemath.php',
        );
        if ($launcher === false) {
            throw new RuntimeException('Unable to resolve the launcher path.');
        }

        $pipes = [];
        $process = proc_open(
            [PHP_BINARY, $launcher, '--version'],
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

        $standardOutput = stream_get_contents($pipes[1]);
        $standardError = stream_get_contents($pipes[2]);
        fclose($pipes[0]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        self::assertSame("ladlemath 0.2.0
", $standardOutput);
        self::assertSame('', $standardError);
        self::assertSame(0, $exitCode);
    }
}
