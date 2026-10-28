<?php

declare(strict_types=1);

// Launcher: loads the classes, runs the program and exits with its code.
spl_autoload_register(static function (string $class): void {
    $prefix = 'Ladlemath\\';
    if (str_starts_with($class, $prefix)) {
        $file = __DIR__ . '/../src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

use Ladlemath\Base\Inputs;
use Ladlemath\Base\Output;
use Ladlemath\Cmd\Cli;

$args = array_slice($argv, 1);
$inputs = new Inputs(
    static function (string $path): ?string {
        $data = @file_get_contents($path);

        return $data === false ? null : $data;
    },
    static function (): string {
        $data = file_get_contents('php://stdin');

        return $data === false ? '' : $data;
    },
);
$manifest = @file_get_contents(__DIR__ . '/../composer.json');
$out = new Output();
$code = Cli::run($args, $inputs, $out, $manifest === false ? null : $manifest);
fwrite(STDOUT, $out->stdout());
fwrite(STDERR, $out->stderr());
exit($code);
