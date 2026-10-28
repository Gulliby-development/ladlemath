<?php

declare(strict_types=1);

namespace Ladlemath\Cmd;

use Ladlemath\Base\Failure;
use Ladlemath\Base\Inputs;
use Ladlemath\Base\Options;
use Ladlemath\Base\Output;
use Ladlemath\Base\Version;

final class Cli
{
    public const USAGE = "usage: ladlemath scale (--serves N | --factor Q) [--promote] [--glyphs] FILE\n"
        . "       ladlemath convert --to metric|us [--glyphs] FILE\n"
        . "       ladlemath merge [--glyphs] FILE...\n"
        . "       ladlemath check FILE...\n"
        . "       ladlemath parse FILE\n";

    /**
     * @param list<string> $args
     */
    public static function run(array $args, Inputs $inputs, Output $out, ?string $manifest = null): int
    {
        if ($args === []) {
            $out->writeErr(self::USAGE);

            return 2;
        }

        if ($args[0] === '--help' || $args[0] === '-h') {
            $out->writeOut(self::USAGE);

            return 0;
        }

        if ($args[0] === '--version' || $args[0] === '-V') {
            $version = Version::fromManifest($manifest);
            if ($version === null) {
                $out->writeErr("ladlemath: version is not known
");

                return 2;
            }

            $out->writeOut('ladlemath ' . $version . "
");

            return 0;
        }

        try {
            $command = $args[0];
            $commandArgs = array_slice($args, 1);

            return match ($command) {
                'scale' => ScaleCommand::run(
                    Options::parse($commandArgs, [
                        'serves' => 'value',
                        'factor' => 'value',
                        'promote' => 'flag',
                        'glyphs' => 'flag',
                    ]),
                    $inputs,
                    $out,
                ),
                'convert' => ConvertCommand::run(
                    Options::parse($commandArgs, [
                        'to' => 'value',
                        'glyphs' => 'flag',
                    ]),
                    $inputs,
                    $out,
                ),
                'merge' => MergeCommand::run(
                    Options::parse($commandArgs, [
                        'glyphs' => 'flag',
                    ]),
                    $inputs,
                    $out,
                ),
                'check' => CheckCommand::run(
                    Options::parse($commandArgs, []),
                    $inputs,
                    $out,
                ),
                'parse' => ParseCommand::run(
                    Options::parse($commandArgs, []),
                    $inputs,
                    $out,
                ),
                default => throw new Failure(2, 'unknown command ' . $command),
            };
        } catch (Failure $failure) {
            $out->writeErr('ladlemath: ' . $failure->getMessage() . "\n");

            return $failure->exitCode;
        }
    }
}
