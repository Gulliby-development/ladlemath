<?php

declare(strict_types=1);

namespace Ladlemath\Tests\Base;

use Ladlemath\Base\Failure;
use Ladlemath\Base\Options;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use RuntimeException;

final class OptionsTest extends TestCase
{
    /**
     * @return list<array{
     *     0: list<string>,
     *     1: array<string, true>,
     *     2: array<string, string>,
     *     3: list<string>,
     *     4: bool,
     *     5: ?string
     * }>
     */
    public static function successfulArguments(): array
    {
        return [
            [['--glyphs', '--glyphs', 'a'], ['glyphs' => true], [], ['a'], true, null],
            [['--serves', '4', '--serves=6'], [], ['serves' => '6'], [], false, '6'],
            [['--serves=a=b'], [], ['serves' => 'a=b'], [], false, 'a=b'],
            [['--serves', '-x', '-'], [], ['serves' => '-x'], ['-'], false, '-x'],
            [['a', '--', '--glyphs', '--', '-'], [], [], ['a', '--glyphs', '--', '-'], false, null],
            [[], [], [], [], false, null],
        ];
    }

    /**
     * @return list<array{0: list<string>, 1: string}>
     */
    public static function invalidArguments(): array
    {
        return [
            [['--x=1'], 'unknown option --x'],
            [['-h'], 'unknown option -h'],
            [['--glyphs=1'], 'option --glyphs takes no value'],
            [['--serves'], 'option --serves needs a value'],
            [['--serves='], 'option --serves needs a non-empty value'],
            [['--serves', ''], 'option --serves needs a non-empty value'],
            [['--x', '--glyphs=1'], 'unknown option --x'],
            [['--glyphs=1', '--x'], 'option --glyphs takes no value'],
        ];
    }

    /**
     * @param list<string> $args
     * @param array<string, true> $expectedFlags
     * @param array<string, string> $expectedValues
     * @param list<string> $expectedOperands
     */
    #[Test]
    #[DataProvider('successfulArguments')]
    public function parsesArguments(
        array $args,
        array $expectedFlags,
        array $expectedValues,
        array $expectedOperands,
        bool $expectedHasGlyphs,
        ?string $expectedServes,
    ): void {
        $options = Options::parse($args, ['glyphs' => 'flag', 'serves' => 'value']);

        self::assertSame($expectedFlags, $options->flags);
        self::assertSame($expectedValues, $options->values);
        self::assertSame($expectedOperands, $options->operands);
        self::assertSame($expectedHasGlyphs, $options->has('glyphs'));
        self::assertSame($expectedServes, $options->value('serves'));

        if ($args === []) {
            self::assertTrue($options->has('glyphs') === false);
            self::assertTrue($options->value('serves') === null);
        }

        if ($args === ['--glyphs', '--glyphs', 'a']) {
            self::assertTrue($options->has('glyphs') === true);
        }
    }

    /**
     * @param list<string> $args
     */
    #[Test]
    #[DataProvider('invalidArguments')]
    public function reportsFirstSyntaxFailure(array $args, string $expectedMessage): void
    {
        try {
            Options::parse($args, ['glyphs' => 'flag', 'serves' => 'value']);
            self::fail('Expected option parsing to fail.');
        } catch (Failure $failure) {
            self::assertInstanceOf(RuntimeException::class, $failure);
            self::assertSame(2, $failure->exitCode);
            self::assertSame($expectedMessage, $failure->getMessage());
            self::assertTrue((new ReflectionProperty(Failure::class, 'exitCode'))->isReadOnly());
        }
    }
}
