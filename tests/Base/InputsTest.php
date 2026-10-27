<?php

declare(strict_types=1);

namespace Ladlemath\Tests\Base;

use Closure;
use Ladlemath\Base\Failure;
use Ladlemath\Base\Inputs;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class InputsTest extends TestCase
{
    #[Test]
    public function fileContentUsesOnlyTheFileReader(): void
    {
        $fileReads = 0;
        $stdinReads = 0;
        $seenPath = '';
        $inputs = new Inputs(
            static function (string $path) use (&$fileReads, &$seenPath): string {
                ++$fileReads;
                $seenPath = $path;

                return 'é';
            },
            static function () use (&$stdinReads): string {
                ++$stdinReads;

                return 'unused';
            },
        );

        self::assertSame('é', $inputs->text('a.txt'));
        self::assertSame(1, $fileReads);
        self::assertSame(0, $stdinReads);
        self::assertSame('a.txt', $seenPath);
    }

    #[Test]
    public function stdinPathUsesOnlyTheStdinReader(): void
    {
        $fileReads = 0;
        $stdinReads = 0;
        $inputs = new Inputs(
            static function (string $path) use (&$fileReads): string {
                ++$fileReads;

                return $path;
            },
            static function () use (&$stdinReads): string {
                ++$stdinReads;

                return 'egg';
            },
        );

        self::assertSame('egg', $inputs->text('-'));
        self::assertSame(0, $fileReads);
        self::assertSame(1, $stdinReads);
    }

    #[Test]
    public function emptyFileContentIsSuccessful(): void
    {
        $stdinReads = 0;
        $inputs = new Inputs(
            static fn (string $path): string => '',
            static function () use (&$stdinReads): string {
                ++$stdinReads;

                return 'unused';
            },
        );

        self::assertSame('', $inputs->text('empty.txt'));
        self::assertSame(0, $stdinReads);
    }

    #[Test]
    public function unreadableFileThrowsAnUndecoratedFailure(): void
    {
        $seenPath = '';
        $stdinReads = 0;
        $inputs = new Inputs(
            static function (string $path) use (&$seenPath): ?string {
                $seenPath = $path;

                return null;
            },
            static function () use (&$stdinReads): string {
                ++$stdinReads;

                return 'unused';
            },
        );

        $this->assertFailure(
            static fn (): string => $inputs->text('missing.txt'),
            'cannot read missing.txt',
        );
        self::assertSame('missing.txt', $seenPath);
        self::assertSame(0, $stdinReads);
    }

    #[Test]
    public function invalidFileUtf8ThrowsAnUndecoratedFailure(): void
    {
        $stdinReads = 0;
        $inputs = new Inputs(
            static fn (string $path): string => "\xFF",
            static function () use (&$stdinReads): string {
                ++$stdinReads;

                return 'unused';
            },
        );

        $this->assertFailure(
            static fn (): string => $inputs->text('bad.txt'),
            'bad.txt: input is not valid UTF-8',
        );
        self::assertSame(0, $stdinReads);
    }

    #[Test]
    public function invalidStdinUtf8ThrowsAnUndecoratedFailure(): void
    {
        $fileReads = 0;
        $inputs = new Inputs(
            static function (string $path) use (&$fileReads): ?string {
                ++$fileReads;

                return null;
            },
            static fn (): string => "2 cups flour\n\xFF\n",
        );

        $this->assertFailure(
            static fn (): string => $inputs->text('-'),
            '<stdin>: input is not valid UTF-8',
        );
        self::assertSame(0, $fileReads);
    }

    #[Test]
    public function validUtf8ContentPreservesBomAndLineEndings(): void
    {
        $content = "\u{FEFF}é\r\n";
        $inputs = new Inputs(
            static fn (string $path): string => $content,
            static fn (): string => '',
        );

        self::assertSame($content, $inputs->text('bom.txt'));
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    public static function displayNameCases(): array
    {
        return [
            ['-', '<stdin>'],
            ['a.txt', 'a.txt'],
        ];
    }

    #[Test]
    #[DataProvider('displayNameCases')]
    public function displayNameUsesTheStdinLabelOnlyForDash(string $path, string $expected): void
    {
        self::assertSame($expected, Inputs::displayName($path));
    }

    /**
     * @param Closure(): string $operation
     */
    private function assertFailure(Closure $operation, string $message): void
    {
        try {
            $operation();
            self::fail('Expected a Failure.');
        } catch (Failure $failure) {
            self::assertSame(Failure::class, $failure::class);
            self::assertSame(2, $failure->exitCode);
            self::assertSame($message, $failure->getMessage());
            self::assertTrue((new ReflectionProperty(Failure::class, 'exitCode'))->isReadOnly());
        }
    }
}
