<?php

declare(strict_types=1);

namespace Wordvel\WordPress;

final class SiteRepository
{
    public function __construct(
        private readonly WordPressCodex $wordpress,
    ) {}

    /**
     * @return array{logo: ?array<string, mixed>, favicon: ?array<string, mixed>}
     */
    public function identity(): array
    {
        $identity = $this->wordpress->call('siteIdentity', []);

        if (! is_array($identity)) {
            return [
                'logo' => null,
                'favicon' => null,
            ];
        }

        return [
            'logo' => is_array($identity['logo'] ?? null) ? $identity['logo'] : null,
            'favicon' => is_array($identity['favicon'] ?? null) ? $identity['favicon'] : null,
        ];
    }

    public function setOption(string $key, mixed $value): void
    {
        $this->wordpress->call('setOption', [
            'key' => $key,
            'value' => $value,
        ]);
    }

    public function setThemeMod(string $key, mixed $value): void
    {
        $this->wordpress->call('setThemeMod', [
            'key' => $key,
            'value' => $value,
        ]);
    }
}