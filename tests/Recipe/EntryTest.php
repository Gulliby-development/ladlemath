<?php

declare(strict_types=1);

namespace Ladlemath\Tests\Recipe;

use Ladlemath\Num\Amount;
use Ladlemath\Num\Fraction;
use Ladlemath\Recipe\Entry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EntryTest extends TestCase
{
    /**
     * @param ?Amount $amount
     */
    #[Test]
    #[DataProvider('entryCases')]
    public function itPreservesMetadataAndRendersTheRequestedQuantity(
        int $line,
        ?Amount $amount,
        string $name,
        string $note,
        string $text,
        string $quantity,
        string $expected,
    ): void {
        $entry = new Entry($line, $amount, $name, $note, $text);

        self::assertSame($line, $entry->line);
        self::assertSame($amount, $entry->amount);
        self::assertSame($name, $entry->name);
        self::assertSame($note, $entry->note);
        self::assertSame($text, $entry->text);
        self::assertSame($expected, $entry->withQuantity($quantity));
    }

    /**
     * @return list<array{0: int, 1: ?Amount, 2: string, 3: string, 4: string, 5: string, 6: string}>
     */
    public static function entryCases(): array
    {
        return [
            [4, new Amount('cup', Fraction::of(2)), 'flour', '', '2 cups flour', '2 cups', '2 cups flour'],
            [7, new Amount('tbsp', Fraction::of(3)), 'butter', 'melted', '3 tbsp butter, melted', '3 tbsp', '3 tbsp butter, melted'],
            [9, null, 'salt', 'to taste', 'salt to taste', '1 tsp', '1 tsp salt, to taste'],
        ];
    }
}
