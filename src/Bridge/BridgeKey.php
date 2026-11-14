<?php

declare(strict_types=1);

namespace Wordvel\Bridge;

/**
 * The shared secret the WordPress bridge sends to Laravel on server-to-server
 * requests. WordPress boots the same Laravel application, so both sides
 * resolve the same value from the same configuration.
 */
final class BridgeKey
{
    public const HEADER = 'X-Wordvel-Bridge-Key';

    public static function value(): string
    {
        $configured = config('wordvel.bridge.key');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        $appKey = config('app.key');

        if (! is_string($appKey) || $appKey === '') {
            return '';
        }

        return hash_hmac('sha256', 'wordvel-bridge', $appKey);
    }

    public static function matches(?string $candidate): bool
    {
        $expected = self::value();

        return $expected !== ''
            && is_string($candidate)
            && $candidate !== ''
            && hash_equals($expected, $candidate);
    }
}
