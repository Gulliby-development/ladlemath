<?php

declare(strict_types=1);

namespace Ladlemath\Merge;

use LogicException;
use Ladlemath\Base\Text;
use Ladlemath\Kitchen\Quantity;
use Ladlemath\Kitchen\Rounding;
use Ladlemath\Num\Amount;
use Ladlemath\Num\Fraction;
use Ladlemath\Recipe\Entry;
use Ladlemath\Unit\Units;

/**
 * @phpstan-type PoolGroup array{
 *     key: string,
 *     family: string,
 *     name: string,
 *     unit: ?string,
 *     lo: ?Fraction,
 *     hi: ?Fraction
 * }
 */
final class Pool
{
    /** @var list<PoolGroup> */
    private array $groups = [];

    public function add(Entry $entry): void
    {
        $key = Text::nameKey($entry->name);
        $amount = $entry->amount;

        if ($amount === null) {
            foreach ($this->groups as $group) {
                if ($group['key'] === $key && $group['family'] === 'none') {
                    return;
                }
            }

            $this->groups[] = [
                'key' => $key,
                'family' => 'none',
                'name' => $entry->name,
                'unit' => null,
                'lo' => null,
                'hi' => null,
            ];

            return;
        }

        $unit = $amount->unit;
        $family = $unit === null ? 'count' : Units::family($unit);
        $factor = Fraction::of($unit === null ? 1 : Units::factor($unit));
        $lo = $amount->lo->mul($factor);
        $hi = ($amount->hi ?? $amount->lo)->mul($factor);

        foreach ($this->groups as $index => $group) {
            if ($group['key'] !== $key || $group['family'] !== $family) {
                continue;
            }

            if ($group['lo'] === null || $group['hi'] === null) {
                throw new LogicException('Invalid quantified pool group.');
            }

            $this->groups[$index]['lo'] = $group['lo']->add($lo);
            $this->groups[$index]['hi'] = $group['hi']->add($hi);

            return;
        }

        $this->groups[] = [
            'key' => $key,
            'family' => $family,
            'name' => $entry->name,
            'unit' => $unit,
            'lo' => $lo,
            'hi' => $hi,
        ];
    }

    /**
     * @return list<string>
     */
    public function lines(bool $glyphs): array
    {
        $groups = $this->groups;

        /**
         * @param PoolGroup $left
         * @param PoolGroup $right
         */
        usort($groups, static function (array $left, array $right): int {
            $nameOrder = strcmp($left['key'], $right['key']);

            if ($nameOrder !== 0) {
                return $nameOrder;
            }

            $leftRank = match ($left['family']) {
                'volume' => 0,
                'mass' => 1,
                'count' => 2,
                'none' => 3,
                default => throw new LogicException('Invalid pool family.'),
            };
            $rightRank = match ($right['family']) {
                'volume' => 0,
                'mass' => 1,
                'count' => 2,
                'none' => 3,
                default => throw new LogicException('Invalid pool family.'),
            };

            return $leftRank <=> $rightRank;
        });

        $lines = [];

        foreach ($groups as $group) {
            if ($group['family'] === 'none') {
                $lines[] = $group['name'];

                continue;
            }

            if ($group['lo'] === null || $group['hi'] === null) {
                throw new LogicException('Invalid quantified pool group.');
            }

            $factor = Fraction::of($group['unit'] === null ? 1 : Units::factor($group['unit']));
            $amount = new Amount(
                $group['unit'],
                $group['lo']->div($factor),
                $group['hi']->div($factor),
            );
            $amount = Rounding::promote($amount);
            $lines[] = Quantity::finish($amount, $glyphs) . ' ' . $group['name'];
        }

        return $lines;
    }
}
