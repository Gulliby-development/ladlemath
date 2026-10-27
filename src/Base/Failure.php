<?php

declare(strict_types=1);

namespace Ladlemath\Base;

use RuntimeException;

final class Failure extends RuntimeException
{
    public function __construct(public readonly int $exitCode, string $message)
    {
        parent::__construct($message);
    }
}
