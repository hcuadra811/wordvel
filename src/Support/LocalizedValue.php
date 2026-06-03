<?php

declare(strict_types=1);

namespace Wordvel\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;

final class LocalizedValue
{
    /**
     * @var array<int, string>
     */
    public const LANGUAGES = ['en', 'es'];

    public static function language(?string $language): string
    {
        if (! is_string($language) || trim($language) === '') {
            return 'en';
        }

        $normalized = strtolower(substr(trim($language), 0, 2));

        return in_array($normalized, self::LANGUAGES, true) ? $normalized : 'en';
    }

    public static function languageFromRequest(Request $request): string
    {
        return self::language(
            $request->header('X-Wordvel-Language')
                ?? $request->header('Accept-Language')
                ?? $request->query('lang'),
        );
    }

    /**
     * @return array<string, string>
     */
    public static function normalize(mixed $value, mixed $existing = null, string $defaultLanguage = 'en'): array
    {
        $defaultLanguage = self::language($defaultLanguage);
        $base = self::localizedArray($existing);
        $next = self::localizedArray($value);

        if ($next !== null) {
            $base = array_replace($base ?? [], $next);
        } elseif (is_scalar($value)) {
            $base[$defaultLanguage] = (string) $value;
        } elseif ($value === null && $base === null) {
            $base = [];
        }

        $base ??= [];
        $fallback = '';

        foreach (self::LANGUAGES as $language) {
            if (isset($base[$language]) && $base[$language] !== '') {
                $fallback = (string) $base[$language];
                break;
            }
        }

        if ($fallback === '' && is_scalar($value)) {
            $fallback = (string) $value;
        } elseif ($fallback === '' && is_scalar($existing)) {
            $fallback = (string) $existing;
        }

        foreach (self::LANGUAGES as $language) {
            $base[$language] = array_key_exists($language, $base)
                ? (string) ($base[$language] ?? '')
                : $fallback;
        }

        return $base;
    }

    public static function resolve(mixed $value, ?string $language = null): mixed
    {
        if ($value instanceof Data) {
            $value = $value->toArray();
        }

        if ($value instanceof Collection) {
            $value = $value->all();
        }

        if (! is_array($value)) {
            return $value;
        }

        if (self::isLocalizedArray($value)) {
            $language = self::language($language);

            return (string) (
                $value[$language]
                    ?? $value['en']
                    ?? collect($value)->first(static fn (mixed $item): bool => is_scalar($item) && (string) $item !== '')
                    ?? ''
            );
        }

        return array_map(
            static fn (mixed $item): mixed => self::resolve($item, $language),
            $value,
        );
    }

    public static function text(mixed $value, ?string $language = null): string
    {
        $resolved = self::resolve($value, $language);

        return is_scalar($resolved) ? (string) $resolved : '';
    }

    public static function searchText(mixed $value): string
    {
        $localized = self::localizedArray($value);

        if ($localized !== null) {
            return implode(' ', array_values($localized));
        }

        return is_scalar($value) ? (string) $value : '';
    }

    private static function isLocalizedArray(array $value): bool
    {
        return array_key_exists('en', $value) || array_key_exists('es', $value);
    }

    /**
     * @return array<string, string>|null
     */
    private static function localizedArray(mixed $value): ?array
    {
        if ($value instanceof Data) {
            $value = $value->toArray();
        }

        if ($value instanceof Collection) {
            $value = $value->all();
        }

        if (! is_array($value) || ! self::isLocalizedArray($value)) {
            return null;
        }

        return collect($value)
            ->filter(static fn (mixed $item, mixed $key): bool => is_string($key) && in_array($key, self::LANGUAGES, true))
            ->map(static fn (mixed $item): string => is_scalar($item) ? (string) $item : '')
            ->all();
    }
}
