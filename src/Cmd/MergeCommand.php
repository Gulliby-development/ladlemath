<?php

declare(strict_types=1);

namespace Ladlemath\Cmd;

use Ladlemath\Base\Failure;
use Ladlemath\Base\Inputs;
use Ladlemath\Base\Options;
use Ladlemath\Base\Output;
use Ladlemath\Base\Text;
use Ladlemath\Merge\Pool;
use Ladlemath\Recipe\Document;

final class MergeCommand
{
    public static function run(Options $options, Inputs $inputs, Output $out): int
    {
        if ($options->operands === []) {
            throw new Failure(2, 'merge needs at least one file');
        }

        $skip = self::skipped($options->value('skip'));
        $pool = new Pool();

        foreach ($options->operands as $operand) {
            $document = Document::strict($operand, $inputs);

            foreach ($document->entries as $entry) {
                if (isset($skip[Text::nameKey($entry->name)])) {
                    continue;
                }

                $pool->add($entry);
            }
        }

        $out->writeOut(Text::lines($pool->lines($options->has('glyphs'))));

        return 0;
    }

    /**
     * The ingredient names of --skip as a set of name keys: a comma-separated list, no empty name.
     *
     * @return array<string, true>
     */
    private static function skipped(?string $value): array
    {
        $names = [];

        if ($value === null) {
            return $names;
        }

        foreach (explode(',', $value) as $name) {
            $key = Text::nameKey($name);

            if ($key === '') {
                throw new Failure(2, 'option --skip needs comma-separated ingredient names');
            }

            $names[$key] = true;
        }

        return $names;
    }
}
