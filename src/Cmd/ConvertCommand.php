<?php

declare(strict_types=1);

namespace Ladlemath\Cmd;

use Ladlemath\Base\Failure;
use Ladlemath\Base\Inputs;
use Ladlemath\Base\Options;
use Ladlemath\Base\Output;
use Ladlemath\Base\Text;
use Ladlemath\Kitchen\Conversion;
use Ladlemath\Kitchen\Quantity;
use Ladlemath\Recipe\Document;

final class ConvertCommand
{
    public static function run(Options $options, Inputs $inputs, Output $out): int
    {
        $to = $options->value('to');
        if ($to === null) {
            throw new Failure(2, 'convert needs --to');
        }

        if ($to !== 'metric' && $to !== 'us') {
            throw new Failure(2, 'invalid --to value: ' . $to);
        }

        if (count($options->operands) !== 1) {
            throw new Failure(2, 'convert needs exactly one file');
        }

        $document = Document::strict($options->operands[0], $inputs);
        $glyphs = $options->has('glyphs');
        $lines = [];

        if ($document->title !== '') {
            $lines[] = 'Title: ' . $document->title;
        }

        if ($document->serves !== null) {
            $lines[] = 'Serves: ' . $document->serves;
        }

        foreach ($document->entries as $entry) {
            if ($entry->amount === null) {
                $lines[] = $entry->text;
                continue;
            }

            $converted = Conversion::convert($entry->amount, $to);
            if ($converted === null) {
                $lines[] = $entry->text;
                continue;
            }

            $lines[] = $entry->withQuantity(Quantity::finish($converted, $glyphs));
        }

        $out->writeOut(Text::lines($lines));

        return 0;
    }
}
