<?php

declare(strict_types=1);

namespace Wordvel\Support;

final class Timecode
{
    /**
     * Format seconds as minutes:seconds:milliseconds (e.g. 23.7 → "0:23:700").
     */
    public static function format(float $seconds): string
    {
        $totalMs = (int) round($seconds * 1000);
        $milliseconds = $totalMs % 1000;
        $totalSeconds = intdiv($totalMs, 1000);
        $secondsPart = $totalSeconds % 60;
        $minutes = intdiv($totalSeconds, 60);

        return sprintf('%d:%02d:%03d', $minutes, $secondsPart, $milliseconds);
    }

    /**
     * Parse minutes:seconds:milliseconds (or plain seconds) into fractional seconds.
     */
    public static function parse(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        if (preg_match('/^(\d+):(\d{1,2}):(\d{1,3})$/', $value, $matches) === 1) {
            return ((int) $matches[1] * 60)
                + (int) $matches[2]
                + ((int) $matches[3]) / 1000;
        }

        if (preg_match('/^(\d+):(\d{1,2})\.(\d{1,3})$/', $value, $matches) === 1) {
            return ((int) $matches[1] * 60)
                + (int) $matches[2]
                + ((int) $matches[3]) / 1000;
        }

        return null;
    }
}