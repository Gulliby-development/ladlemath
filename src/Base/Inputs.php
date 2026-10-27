<?php

declare(strict_types=1);

namespace Ladlemath\Base;

use Closure;

final class Inputs
{
    /** @var Closure(string): ?string */
    private Closure $readFile;

    /** @var Closure(): string */
    private Closure $readStdin;

    /**
     * @param Closure(string): ?string $readFile
     * @param Closure(): string $readStdin
     */
    public function __construct(Closure $readFile, Closure $readStdin)
    {
        $this->readFile = $readFile;
        $this->readStdin = $readStdin;
    }

    public function text(string $path): string
    {
        $name = self::displayName($path);

        if ($path === '-') {
            $content = ($this->readStdin)();
        } else {
            $content = ($this->readFile)($path);

            if ($content === null) {
                throw new Failure(2, 'cannot read ' . $path);
            }
        }

        if (!Text::isUtf8($content)) {
            throw new Failure(2, $name . ': input is not valid UTF-8');
        }

        return $content;
    }

    public static function displayName(string $path): string
    {
        return $path === '-' ? '<stdin>' : $path;
    }
}
