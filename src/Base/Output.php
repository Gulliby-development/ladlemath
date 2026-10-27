<?php

declare(strict_types=1);

namespace Ladlemath\Base;

final class Output
{
    private string $out = '';

    private string $err = '';

    public function writeOut(string $text): void
    {
        $this->out .= $text;
    }

    public function writeErr(string $text): void
    {
        $this->err .= $text;
    }

    public function stdout(): string
    {
        return $this->out;
    }

    public function stderr(): string
    {
        return $this->err;
    }
}
