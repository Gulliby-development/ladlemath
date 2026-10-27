<?php

declare(strict_types=1);

namespace Ladlemath\Recipe;

use Ladlemath\Base\Failure;
use Ladlemath\Base\Inputs;
use Ladlemath\Base\Text;
use Ladlemath\Model\Problem;

final class Document
{
    public readonly string $title;

    public readonly ?int $serves;

    /** @var list<Entry> */
    public readonly array $entries;

    /** @var list<Problem> */
    public readonly array $problems;

    /**
     * @param list<Entry> $entries
     * @param list<Problem> $problems
     */
    private function __construct(string $title, ?int $serves, array $entries, array $problems)
    {
        $this->title = $title;
        $this->serves = $serves;
        $this->entries = $entries;
        $this->problems = $problems;
    }

    public static function load(string $text): self
    {
        if (str_starts_with($text, "\u{FEFF}")) {
            $text = substr($text, strlen("\u{FEFF}"));
        }

        if ($text === '') {
            return new self('', null, [], []);
        }

        $splitLines = explode("\n", $text);
        $splitCount = count($splitLines);
        $hasFinalLf = str_ends_with($text, "\n");
        $lineCount = $splitCount - ($hasFinalLf ? 1 : 0);
        $title = '';
        $serves = null;
        $entries = [];
        $problems = [];

        for ($index = 0; $index < $lineCount; $index++) {
            $line = $splitLines[$index];
            if ($index < $splitCount - 1 && str_ends_with($line, "\r")) {
                $line = substr($line, 0, -1);
            }

            $lineNumber = $index + 1;
            $codePoints = Text::cps($line);
            foreach ($codePoints as $columnIndex => $character) {
                $codePoint = ord($character);
                if (($codePoint < 32 && $character !== "\t") || $codePoint === 127) {
                    $problems[] = new Problem(
                        $lineNumber,
                        $columnIndex + 1,
                        'LM008',
                        sprintf('control character U+%04X', $codePoint),
                    );
                }
            }

            $length = count($codePoints);
            if ($length > 200) {
                $problems[] = new Problem(
                    $lineNumber,
                    201,
                    'LM007',
                    'line has ' . $length . ' characters, the limit is 200',
                );
            }

            $trimmedLine = Text::trimBlanks($line);
            if ($trimmedLine === '' || str_starts_with($trimmedLine, '#')) {
                continue;
            }

            if (str_starts_with($line, 'Title:')) {
                $title = Text::trimBlanks(substr($line, strlen('Title:')));
                continue;
            }

            if (str_starts_with($line, 'Serves:')) {
                $value = Text::trimBlanks(substr($line, strlen('Serves:')));
                if (preg_match('/\A[0-9]{1,4}\z/', $value) !== 1 || (int) $value === 0) {
                    $problems[] = new Problem(
                        $lineNumber,
                        8,
                        'LM004',
                        'invalid serves value ' . Text::shown($value),
                    );
                } elseif ($serves !== null) {
                    $problems[] = new Problem($lineNumber, 1, 'LM005', 'serves is already set');
                } else {
                    $serves = (int) $value;
                }

                continue;
            }

            $entry = Line::parse($line, $lineNumber);
            if ($entry instanceof Problem) {
                $problems[] = $entry;
            } else {
                $entries[] = $entry;
            }
        }

        return new self($title, $serves, $entries, $problems);
    }

    public static function strict(string $path, Inputs $inputs): self
    {
        $document = self::load($inputs->text($path));
        if ($document->problems !== []) {
            throw new Failure(2, $document->problems[0]->text(Inputs::displayName($path)));
        }

        return $document;
    }
}
