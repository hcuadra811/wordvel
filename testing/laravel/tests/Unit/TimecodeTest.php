<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Wordvel\Support\Timecode;

final class TimecodeTest extends TestCase
{
    public function test_it_formats_seconds(): void
    {
        $this->assertSame('0:23:700', Timecode::format(23.7));
        $this->assertSame('1:05:000', Timecode::format(65));
    }

    #[DataProvider('parseProvider')]
    public function test_it_parses_flexible_input(string $input, ?float $expected): void
    {
        $parsed = Timecode::parse($input);

        if ($expected === null) {
            $this->assertNull($parsed);
            return;
        }

        $this->assertNotNull($parsed);
        $this->assertEqualsWithDelta($expected, $parsed, 0.001);
    }

    public static function parseProvider(): array
    {
        return [
            'empty' => ['', null],
            'seconds' => ['23', 23.0],
            'decimal seconds' => ['23.5', 23.5],
            'minutes seconds' => ['1:23', 83.0],
            'minutes seconds short' => ['0:5', 5.0],
            'seconds milliseconds' => ['23:700', 23.7],
            'seconds milliseconds short' => ['23:7', 23.7],
            'full timecode' => ['0:23:700', 23.7],
            'full short segments' => ['1:2:3', 62.3],
            'legacy decimal' => ['1:23.7', 83.7],
            'numeric string seconds' => ['45.25', 45.25],
        ];
    }
}