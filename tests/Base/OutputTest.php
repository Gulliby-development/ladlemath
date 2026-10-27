<?php

declare(strict_types=1);

namespace Ladlemath\Tests\Base;

use Ladlemath\Base\Output;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class OutputTest extends TestCase
{
    #[Test]
    public function buffersWritesIndependentlyAndKeepsReadsNonDestructive(): void
    {
        $output = new Output();

        self::assertSame('', $output->stdout());
        self::assertSame('', $output->stderr());

        $output->writeOut('a');
        $output->writeErr("e\n");
        $output->writeOut("b\n");
        $output->writeErr('!');

        self::assertSame("ab\n", $output->stdout());
        self::assertSame("e\n!", $output->stderr());
        self::assertSame("ab\n", $output->stdout());
        self::assertSame("e\n!", $output->stderr());
    }
}
