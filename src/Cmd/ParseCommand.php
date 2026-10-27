<?php

declare(strict_types=1);

namespace Ladlemath\Cmd;

use Ladlemath\Base\Failure;
use Ladlemath\Base\Inputs;
use Ladlemath\Base\Options;
use Ladlemath\Base\Output;
use Ladlemath\Base\Text;
use Ladlemath\Num\Show;
use Ladlemath\Recipe\Document;

final class ParseCommand
{
    public static function run(Options $options, Inputs $inputs, Output $out): int
    {
        $operands = $options->operands;

        if (count($operands) !== 1) {
            throw new Failure(2, 'parse needs exactly one file');
        }

        $document = Document::strict($operands[0], $inputs);
        $rows = [];

        foreach ($document->entries as $entry) {
            $quantity = '-';
            $unit = '-';

            if ($entry->amount !== null) {
                $quantity = Show::mixed($entry->amount->lo, false);

                if ($entry->amount->hi !== null) {
                    $quantity .= ' to ' . Show::mixed($entry->amount->hi, false);
                }

                $unit = $entry->amount->unit ?? '-';
            }

            $rows[] = [
                (string) $entry->line,
                $quantity,
                $unit,
                $entry->name,
                $entry->note === '' ? '-' : $entry->note,
            ];
        }

        $headings = ['LINE', 'QTY', 'UNIT', 'NAME', 'NOTE'];
        $lines = [];
        $widths = [
            Text::length($headings[0]),
            Text::length($headings[1]),
            Text::length($headings[2]),
            Text::length($headings[3]),
            Text::length($headings[4]),
        ];

        foreach ($rows as $row) {
            foreach ($row as $column => $cell) {
                $widths[$column] = max($widths[$column], Text::length($cell));
            }
        }

        $rows = [$headings, ...$rows];

        foreach ($rows as $row) {
            $cells = [];

            foreach ($row as $column => $cell) {
                $padding = $widths[$column] - Text::length($cell);
                $cells[] = $cell . str_repeat(' ', $padding);
            }

            $lines[] = rtrim(implode('  ', $cells));
        }

        $out->writeOut(Text::lines($lines));

        return 0;
    }
}
