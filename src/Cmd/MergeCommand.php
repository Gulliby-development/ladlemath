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

        $pool = new Pool();

        foreach ($options->operands as $operand) {
            $document = Document::strict($operand, $inputs);

            foreach ($document->entries as $entry) {
                $pool->add($entry);
            }
        }

        $out->writeOut(Text::lines($pool->lines($options->has('glyphs'))));

        return 0;
    }
}
