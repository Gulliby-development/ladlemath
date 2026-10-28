<?php

declare(strict_types=1);

namespace Ladlemath\Tests\Base;

use Ladlemath\Base\Version;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class VersionTest extends TestCase
{
    /**
     * @return list<array{0: ?string, 1: ?string}>
     */
    public static function literalCases(): array
    {
        return [
            [null, null],
            ['', null],
            ['{}', null],
            ['{"version": "0.2.0"}', '0.2.0'],
            ['{"version":"10.20.30"}', '10.20.30'],
            ["{
    \"name\": \"a/b\",
    \"version\" :	\"1.2.3\"
}", '1.2.3'],
            ['{"version": "1.2"}', null],
            ['{"version": "v1.2.3"}', null],
            ['{"version": "1.2.3-beta"}', null],
            ['{"version": 1}', null],
            ['{"Version": "1.2.3"}', null],
            ['{"version": "1.2", "extra": {"version": "3.4.5"}}', '3.4.5'],
            ['{"version": "1.0.0", "extra": {"version": "3.4.5"}}', '1.0.0'],
        ];
    }

    #[Test]
    #[DataProvider('literalCases')]
    public function readsTheVersionField(?string $manifest, ?string $expected): void
    {
        self::assertCount(13, self::literalCases());
        self::assertSame($expected, Version::fromManifest($manifest));
    }

    #[Test]
    public function theProjectManifestHoldsVersion020(): void
    {
        $manifest = file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'composer.json');

        self::assertSame('0.2.0', Version::fromManifest($manifest === false ? null : $manifest));
    }
}
