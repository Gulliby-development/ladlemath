<?php

declare(strict_types=1);

namespace Ladlemath\Tests\Recipe;

use Ladlemath\Model\Problem;
use Ladlemath\Num\Amount;
use Ladlemath\Recipe\Entry;
use Ladlemath\Recipe\Line;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class LineTest extends TestCase
{
    #[Test]
    #[DataProvider('validLineCases')]
    public function itParsesLiteralIngredientLines(
        string $line,
        ?int $loNum,
        ?int $loDen,
        ?int $hiNum,
        ?int $hiDen,
        ?string $unit,
        string $name,
        string $note,
        string $text,
    ): void {
        $result = Line::parse($line, 37);
        self::assertInstanceOf(Entry::class, $result);

        self::assertSame(37, $result->line);
        self::assertSame($name, $result->name);
        self::assertSame($note, $result->note);
        self::assertSame($text, $result->text);

        if ($loNum === null) {
            self::assertNull($result->amount);

            return;
        }

        self::assertInstanceOf(Amount::class, $result->amount);
        self::assertSame($unit, $result->amount->unit);
        self::assertSame($loNum, $result->amount->lo->num);
        self::assertSame($loDen, $result->amount->lo->den);

        if ($hiNum === null) {
            self::assertNull($result->amount->hi);
        } else {
            self::assertNotNull($result->amount->hi);
            self::assertSame($hiNum, $result->amount->hi->num);
            self::assertSame($hiDen, $result->amount->hi->den);
        }
    }

    /**
     * @return list<array{0: string, 1: ?int, 2: ?int, 3: ?int, 4: ?int, 5: ?string, 6: string, 7: string, 8: string}>
     */
    public static function validLineCases(): array
    {
        return [
            ['2 cups flour', 2, 1, null, null, 'cup', 'flour', '', '2 cups flour'],
            ['1 1/2 cups all-purpose flour', 3, 2, null, null, 'cup', 'all-purpose flour', '', '1 1/2 cups all-purpose flour'],
            ['½ tsp salt', 1, 2, null, null, 'tsp', 'salt', '', '½ tsp salt'],
            ['1½ tbsp sugar, packed', 3, 2, null, null, 'tbsp', 'sugar', 'packed', '1½ tbsp sugar, packed'],
            ['250ml milk', 250, 1, null, null, 'ml', 'milk', '', '250ml milk'],
            ['250 ML whole  milk', 250, 1, null, null, 'ml', 'whole  milk', '', '250 ML whole  milk'],
            ['2-3 tbsp butter, melted', 2, 1, 3, 1, 'tbsp', 'butter', 'melted', '2-3 tbsp butter, melted'],
            ['2 to 3 tbsp butter', 2, 1, 3, 1, 'tbsp', 'butter', '', '2 to 3 tbsp butter'],
            ['2 – 3 tbsp butter', 2, 1, 3, 1, 'tbsp', 'butter', '', '2 – 3 tbsp butter'],
            ['1 - 2 cups rice', 1, 1, 2, 1, 'cup', 'rice', '', '1 - 2 cups rice'],
            ['1-1/2 cups rice', 3, 2, null, null, 'cup', 'rice', '', '1-1/2 cups rice'],
            ['1.5 kg potatoes', 3, 2, null, null, 'kg', 'potatoes', '', '1.5 kg potatoes'],
            ['.5 l water', 1, 2, null, null, 'l', 'water', '', '.5 l water'],
            ['3 eggs', 3, 1, null, null, null, 'eggs', '', '3 eggs'],
            ['1 large egg', 1, 1, null, null, null, 'large egg', '', '1 large egg'],
            ['salt to taste', null, null, null, null, null, 'salt to taste', '', 'salt to taste'],
            ['  	pinch of salt  ', null, null, null, null, null, 'pinch of salt', '', 'pinch of salt'],
            ['2 cups of flour', 2, 1, null, null, 'cup', 'flour', '', '2 cups of flour'],
            ['2 Cups OF flour', 2, 1, null, null, 'cup', 'flour', '', '2 Cups OF flour'],
            ['2 cups offal', 2, 1, null, null, 'cup', 'offal', '', '2 cups offal'],
            ['2 - ripe bananas', 2, 1, null, null, null, '- ripe bananas', '', '2 - ripe bananas'],
            ['0 to 2 eggs', 0, 1, 2, 1, null, 'eggs', '', '0 to 2 eggs'],
            ['2 gx5', 2, 1, null, null, null, 'gx5', '', '2 gx5'],
            ['2 tsp. salt', 2, 1, null, null, 'tsp', 'salt', '', '2 tsp. salt'],
            ['2 cups of', 2, 1, null, null, 'cup', 'of', '', '2 cups of'],
            ['2 eggs, large, fresh', 2, 1, null, null, null, 'eggs', 'large, fresh', '2 eggs, large, fresh'],
            ['½ cup café', 1, 2, null, null, 'cup', 'café', '', '½ cup café'],
            ['2 tsp.. salt', 2, 1, null, null, null, 'tsp.. salt', '', '2 tsp.. salt'],
            ['2 of eggs', 2, 1, null, null, null, 'of eggs', '', '2 of eggs'],
            ['2	TSP	of	salt', 2, 1, null, null, 'tsp', 'salt', '', '2	TSP	of	salt'],
        ];
    }

    #[Test]
    #[DataProvider('problemLineCases')]
    public function itReportsLiteralIngredientLineProblems(
        string $line,
        int $column,
        string $code,
        string $message,
    ): void {
        $result = Line::parse($line, 37);
        self::assertInstanceOf(Problem::class, $result);

        self::assertSame(37, $result->line);
        self::assertSame($column, $result->col);
        self::assertSame($code, $result->code);
        self::assertSame($message, $result->message);
        self::assertSame("recipe.txt:37:$column: $code $message", $result->text('recipe.txt'));
    }

    /**
     * @return list<array{0: string, 1: int, 2: string, 3: string}>
     */
    public static function problemLineCases(): array
    {
        return [
            ['1/2 cup', 8, 'LM003', 'missing ingredient name'],
            ['2 cups', 7, 'LM003', 'missing ingredient name'],
            ['2 cups,', 8, 'LM003', 'missing ingredient name'],
            ['2 cups , note', 14, 'LM003', 'missing ingredient name'],
            ['0 cups flour', 1, 'LM006', 'quantity is zero'],
            ['3 to 2 cups flour', 1, 'LM002', 'range is not ascending'],
            ['2 - 2 cups flour', 1, 'LM002', 'range is not ascending'],
            ['1/0 cup flour', 1, 'LM001', 'invalid quantity'],
            ['1234567 g flour', 1, 'LM001', 'invalid quantity'],
            ['12345/2 g flour', 1, 'LM001', 'invalid quantity'],
            ['1.23456 g flour', 1, 'LM001', 'invalid quantity'],
            ['  0 eggs', 3, 'LM006', 'quantity is zero'],
            ['2 to 1/0 eggs', 1, 'LM001', 'invalid quantity'],
            ['  ½ cup  ', 8, 'LM003', 'missing ingredient name'],
        ];
    }
}
