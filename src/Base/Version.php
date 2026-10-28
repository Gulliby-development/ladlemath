<?php

declare(strict_types=1);

namespace Ladlemath\Base;

final class Version
{
    /**
     * The first "version" field of a composer.json text whose value has three numbers, or null.
     */
    public static function fromManifest(?string $manifest): ?string
    {
        if ($manifest === null) {
            return null;
        }

        if (preg_match('/"version"\s*:\s*"([0-9]+\.[0-9]+\.[0-9]+)"/', $manifest, $match) !== 1) {
            return null;
        }

        return $match[1];
    }
}
