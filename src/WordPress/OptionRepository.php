<?php

declare(strict_types=1);

namespace Wordvel\WordPress;

final class OptionRepository
{
    public function __construct(
        private readonly WordPressCodex $wordpress,
    ) {}

    public function value(string $key, mixed $default = null): mixed
    {
        $result = $this->wordpress->call('option', [
            'key' => $key,
            'default' => $default,
        ]);

        return $result ?? $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function array(string $key): array
    {
        $value = $this->value($key);

        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            $decoded = @unserialize($value, ['allowed_classes' => false]);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }
}
