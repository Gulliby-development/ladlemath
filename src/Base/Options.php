<?php

declare(strict_types=1);

namespace Ladlemath\Base;

final class Options
{
    /**
     * @param array<string, true> $flags
     * @param array<string, string> $values
     * @param list<string> $operands
     */
    private function __construct(
        public readonly array $flags,
        public readonly array $values,
        public readonly array $operands,
    ) {
    }

    /**
     * @param list<string> $args
     * @param array<string, string> $spec
     */
    public static function parse(array $args, array $spec): self
    {
        $flags = [];
        $values = [];
        $operands = [];
        $optionsEnabled = true;
        $count = count($args);

        for ($i = 0; $i < $count; $i++) {
            $argument = $args[$i];

            if ($optionsEnabled && $argument === '--') {
                $optionsEnabled = false;

                continue;
            }

            if (!$optionsEnabled || $argument === '-' || !str_starts_with($argument, '-')) {
                $operands[] = $argument;

                continue;
            }

            $equals = strpos($argument, '=');
            $option = $equals === false ? $argument : substr($argument, 0, $equals);

            if (!str_starts_with($option, '--')) {
                throw new Failure(2, 'unknown option ' . $option);
            }

            $name = substr($option, 2);

            if (!array_key_exists($name, $spec)) {
                throw new Failure(2, 'unknown option ' . $option);
            }

            $kind = $spec[$name];

            if ($kind === 'flag') {
                if ($equals !== false) {
                    throw new Failure(2, 'option ' . $option . ' takes no value');
                }

                $flags[$name] = true;

                continue;
            }

            if ($kind !== 'value') {
                throw new Failure(2, 'unknown option ' . $option);
            }

            if ($equals !== false) {
                $value = substr($argument, $equals + 1);
            } else {
                if ($i + 1 >= $count) {
                    throw new Failure(2, 'option ' . $option . ' needs a value');
                }

                $i++;
                $value = $args[$i];
            }

            if ($value === '') {
                throw new Failure(2, 'option ' . $option . ' needs a non-empty value');
            }

            $values[$name] = $value;
        }

        return new self($flags, $values, $operands);
    }

    public function has(string $name): bool
    {
        return isset($this->flags[$name]);
    }

    public function value(string $name): ?string
    {
        return $this->values[$name] ?? null;
    }
}
