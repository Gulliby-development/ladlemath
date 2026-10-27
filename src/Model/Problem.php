<?php

declare(strict_types=1);

namespace Ladlemath\Model;

final class Problem
{
    public function __construct(
        public readonly int $line,
        public readonly int $col,
        public readonly string $code,
        public readonly string $message,
    ) {
    }

    public function at(int $line): self
    {
        return new self($line, $this->col, $this->code, $this->message);
    }

    public function text(string $name): string
    {
        return "$name:$this->line:$this->col: $this->code $this->message";
    }
}
