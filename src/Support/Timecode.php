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
        $totalMs = (int) round(max(0, $seconds) * 1000);
        $milliseconds = $totalMs % 1000;
        $totalSeconds = intdiv($totalMs, 1000);
        $secondsPart = $totalSeconds % 60;
        $minutes = intdiv($totalSeconds, 60);

        return sprintf('%d:%02d:%03d', $minutes, $secondsPart, $milliseconds);
    }

    /**
     * Parse flexible time input into fractional seconds.
     *
     * Accepts plain seconds ("23", "23.5"), minutes:seconds ("1:23", "01:05"),
     * seconds:milliseconds ("23:700", "23:7"), or minutes:seconds:milliseconds ("0:23:700").
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

        $value = trim(str_replace(',', '.', $value));

        if ($value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        if (preg_match('/^(\d+):(\d{1,2})\.(\d{1,3})$/', $value, $matches) === 1) {
            return ((int) $matches[1] * 60)
                + (int) $matches[2]
                + self::parseMillisecondsSegment($matches[3]) / 1000;
        }

        $parts = array_map(static fn (string $part): string => trim($part), explode(':', $value));
        $parts = array_values(array_filter($parts, static fn (string $part): bool => $part !== ''));

        return match (count($parts)) {
            1 => self::parseSingleSegment($parts[0]),
            2 => self::parseTwoSegments($parts[0], $parts[1]),
            3 => self::parseThreeSegments($parts[0], $parts[1], $parts[2]),
            default => null,
        };
    }

    private static function parseSingleSegment(string $segment): ?float
    {
        if ($segment === '' || ! is_numeric($segment)) {
            return null;
        }

        return (float) $segment;
    }

    private static function parseTwoSegments(string $first, string $second): ?float
    {
        if ($first === '' || $second === '') {
            return null;
        }

        if (! ctype_digit($first) || ! is_numeric($second)) {
            return null;
        }

        $minutes = (int) $first;

        if (self::looksLikeSecondsMillisecondsPair($minutes, $second)) {
            return $minutes + self::parseMillisecondsSegment($second) / 1000;
        }

        return ($minutes * 60) + (float) $second;
    }

    private static function looksLikeSecondsMillisecondsPair(int $seconds, string $milliseconds): bool
    {
        if (! ctype_digit($milliseconds)) {
            return false;
        }

        $length = strlen($milliseconds);

        if ($length === 3) {
            return true;
        }

        if ($seconds >= 10) {
            return $length >= 1 && $length <= 2;
        }

        return false;
    }

    private static function parseThreeSegments(string $minutes, string $seconds, string $milliseconds): ?float
    {
        if ($minutes === '' || $seconds === '' || $milliseconds === '') {
            return null;
        }

        if (! ctype_digit($minutes) || ! ctype_digit($seconds)) {
            return null;
        }

        $secondsValue = (int) $seconds;

        if ($secondsValue < 0 || $secondsValue > 59) {
            return null;
        }

        return ((int) $minutes * 60)
            + $secondsValue
            + self::parseMillisecondsSegment($milliseconds) / 1000;
    }

    private static function parseMillisecondsSegment(string $segment): int
    {
        $digits = preg_replace('/\D/', '', $segment) ?? '';

        if ($digits === '') {
            return 0;
        }

        if (strlen($digits) > 3) {
            $digits = substr($digits, 0, 3);
        }

        return min(999, (int) str_pad($digits, 3, '0', STR_PAD_RIGHT));
    }
}