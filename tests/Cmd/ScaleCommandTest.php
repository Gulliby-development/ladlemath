<?php

declare(strict_types=1);

namespace Ladlemath\Tests\Cmd;

use Ladlemath\Base\Failure;
use Ladlemath\Base\Inputs;
use Ladlemath\Base\Options;
use Ladlemath\Base\Output;
use Ladlemath\Cmd\ScaleCommand;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ScaleCommandTest extends TestCase
{
    /**
     * @return list<array{0: list<string>, 1: ?string, 2: string, 3: ?string, 4: int}>
     */
    public static function literalCases(): array
    {
        return [
            [
                ['--serves', '6', 'recipe.txt'],
                "Title: Pancakes\nServes: 4\n\n1 1/2 cups all-purpose flour\n2 tbsp sugar\n\u{00BD} tsp salt\n2-3 tbsp butter, melted\n1 large egg\n250 ml milk\nsalt to taste\n",
                "Title: Pancakes\nServes: 6\n2 1/4 cups all-purpose flour\n3 tbsp sugar\n3/4 tsp salt\n3 to 4 1/2 tbsp butter, melted\n1 1/2 large egg\n380 ml milk\nsalt to taste\n",
                null,
                1,
            ],
            [
                ['--factor', '3', '--promote', 'recipe.txt'],
                "Title: Pancakes\nServes: 4\n\n1 1/2 cups all-purpose flour\n2 tbsp sugar\n\u{00BD} tsp salt\n2-3 tbsp butter, melted\n1 large egg\n250 ml milk\nsalt to taste\n",
                "Title: Pancakes\nServes: 12\n4 1/2 cups all-purpose flour\n3/8 cup sugar\n1 1/2 tsp salt\n3/8 to 5/8 cup butter, melted\n3 large egg\n750 ml milk\nsalt to taste\n",
                null,
                1,
            ],
            [
                ['--factor=.5', 'recipe.txt'],
                "Title: Tea\nServes: 4\n2 cups flour\nsalt to taste\n",
                "Title: Tea\nServes: 2\n1 cup flour\nsalt to taste\n",
                null,
                1,
            ],
            [
                ['--factor=100000000000000000000/200000000000000000000', '--glyphs', 'recipe.txt'],
                "1 tsp salt\n1 cup flour\n",
                "\u{00BD} tsp salt\n\u{00BD} cup flour\n",
                null,
                1,
            ],
            [
                ['--factor=1', 'recipe.txt'],
                "Serves: 9999\n1 egg\n",
                "Serves: 9999\n1 egg\n",
                null,
                1,
            ],
            [
                ['--factor=2', 'recipe.txt'],
                "Serves: 9999\n1 egg\n",
                "2 egg\n",
                null,
                1,
            ],
            [
                ['--serves=2', '--factor=2', 'recipe.txt'],
                "1 cup flour\n",
                '',
                'scale takes either --serves or --factor',
                0,
            ],
            [
                ['recipe.txt', 'other.txt'],
                "1 cup flour\n",
                '',
                'scale needs --serves or --factor',
                0,
            ],
            [
                ['--serves=0'],
                null,
                '',
                'invalid --serves value: 0',
                0,
            ],
            [
                ['--factor=1 1/2'],
                null,
                '',
                'invalid --factor value: 1 1/2',
                0,
            ],
            [
                ['--factor=2', 'first.txt', 'second.txt'],
                null,
                '',
                'scale needs exactly one file',
                0,
            ],
            [
                ['--serves=6', 'recipe.txt'],
                "1 cup flour\n",
                '',
                'recipe.txt: no Serves line',
                1,
            ],
            [
                ['--factor=2', 'recipe.txt'],
                "0 cups flour\n2 cups sugar\n",
                '',
                'recipe.txt:1:1: LM006 quantity is zero',
                1,
            ],
            [
                ['--factor=2000', 'recipe.txt'],
                "1 cup flour\n999999 cups sugar\n",
                '',
                'quantity too large',
                1,
            ],
        ];
    }

    /**
     * @param list<string> $arguments
     */
    #[Test]
    #[DataProvider('literalCases')]
    public function scalesRecipesOrThrowsBeforePublishingOutput(
        array $arguments,
        ?string $fileText,
        string $expectedOutput,
        ?string $expectedMessage,
        int $expectedReads,
    ): void {
        $readCount = 0;
        $inputs = new Inputs(
            static function (string $path) use (&$readCount, $fileText): ?string {
                $readCount++;

                return $path === 'recipe.txt' ? $fileText : null;
            },
            static fn (): string => '',
        );
        $output = new Output();

        try {
            $options = Options::parse($arguments, [
                'serves' => 'value',
                'factor' => 'value',
                'promote' => 'flag',
                'glyphs' => 'flag',
            ]);
            $result = ScaleCommand::run($options, $inputs, $output);

            self::assertNull($expectedMessage);
            self::assertSame(0, $result);
            self::assertSame($expectedOutput, $output->stdout());
            self::assertSame('', $output->stderr());
        } catch (Failure $failure) {
            self::assertNotNull($expectedMessage);
            self::assertSame(2, $failure->exitCode);
            self::assertSame($expectedMessage, $failure->getMessage());
            self::assertSame('', $output->stdout());
            self::assertSame('', $output->stderr());
        }

        self::assertSame($expectedReads, $readCount);
    }
}
