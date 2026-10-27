<?php

declare(strict_types=1);

namespace Ladlemath\Num;

final class Amount
{
    public function __construct(
        public readonly ?string $unit,
        public readonly Fraction $lo,
        public readonly ?Fraction $hi = null,
    ) {
    }

    public function times(Fraction $factor): self
    {
        return new self(
            $this->unit,
            $this->lo->mul($factor),
            $this->hi?->mul($factor),
        );
    }

    public function dividedBy(Fraction $divisor, ?string $unit): self
    {
        return new self(
            $unit,
            $this->lo->div($divisor),
            $this->hi?->div($divisor),
        );
    }
}
