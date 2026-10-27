<?php

declare(strict_types=1);

namespace Ladlemath\Tests\Model;

use Ladlemath\Model\Problem;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ProblemTest extends TestCase
{
    #[Test]
    public function constructorExposesReadonlyFields(): void
    {
        $problem = new Problem(3, 7, 'LM003', 'missing ingredient name');

        self::assertSame(3, $problem->line);
        self::assertSame(7, $problem->col);
        self::assertSame('LM003', $problem->code);
        self::assertSame('missing ingredient name', $problem->message);

        $reflection = new \ReflectionClass($problem);
        foreach (['line', 'col', 'code', 'message'] as $field) {
            self::assertTrue($reflection->getProperty($field)->isReadOnly());
        }
    }

    #[Test]
    public function textFormatsDiagnosticExactly(): void
    {
        $problem = new Problem(3, 7, 'LM003', 'missing ingredient name');

        self::assertSame(
            'a.txt:3:7: LM003 missing ingredient name',
            $problem->text('a.txt'),
        );
    }

    #[Test]
    public function atReturnsDistinctProblemAndPreservesOriginal(): void
    {
        $original = new Problem(3, 7, 'LM003', 'missing ingredient name');
        $relocated = $original->at(9);

        self::assertNotSame($original, $relocated);
        self::assertSame(9, $relocated->line);
        self::assertSame(7, $relocated->col);
        self::assertSame('LM003', $relocated->code);
        self::assertSame('missing ingredient name', $relocated->message);

        self::assertSame(3, $original->line);
        self::assertSame(7, $original->col);
        self::assertSame('LM003', $original->code);
        self::assertSame('missing ingredient name', $original->message);
    }
}
