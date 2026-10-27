<?php

declare(strict_types=1);

namespace Ladlemath\Cmd;

use Ladlemath\Base\Failure;
use Ladlemath\Base\Inputs;
use Ladlemath\Base\Options;
use Ladlemath\Base\Output;
use Ladlemath\Base\Text;
use Ladlemath\Recipe\Document;

final class CheckCommand
{
    public static function run(Options $options, Inputs $inputs, Output $out): int
    {
        if ($options->operands === []) {
            throw new Failure(2, 'check needs at least one file');
        }

        $diagnostics = [];
        $problemCount = 0;
        $affectedFileCount = 0;
        $entryCount = 0;

        foreach ($options->operands as $path) {
            $document = Document::load($inputs->text($path));
            $displayName = Inputs::displayName($path);
            $entryCount += count($document->entries);

            if ($document->problems !== []) {
                $affectedFileCount++;
            }

            foreach ($document->problems as $problem) {
                $diagnostics[] = $problem->text($displayName);
                $problemCount++;
            }
        }

        if ($problemCount > 0) {
            $diagnostics[] = Text::plural($problemCount, 'problem')
                . ' in ' . Text::plural($affectedFileCount, 'file');
            $out->writeOut(Text::lines($diagnostics));

            return 1;
        }

        $out->writeOut(Text::lines([
            'ok, ' . Text::plural($entryCount, 'ingredient line')
                . ' in ' . Text::plural(count($options->operands), 'file'),
        ]));

        return 0;
    }
}
