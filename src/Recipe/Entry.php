<?php

declare(strict_types=1);

namespace Ladlemath\Recipe;

use Ladlemath\Num\Amount;

final class Entry
{
    public function __construct(
        public readonly int $line,
        public readonly ?Amount $amount,
        public readonly string $name,
        public readonly string $note,
        public readonly string $text,
    ) {
    }

    public function withQuantity(string $quantity): string
    {
        $entry = $quantity . ' ' . $this->name;

        if ($this->note !== '') {
            $entry .= ', ' . $this->note;
        }

        return $entry;
    }
}
