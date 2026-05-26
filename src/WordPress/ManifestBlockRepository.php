<?php

declare(strict_types=1);

namespace Wordvel\WordPress;

final class ManifestBlockRepository
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function keyed(): array
    {
        $path = storage_path('wordvel/manifest.json');
        $manifest = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
        $blocks = is_array($manifest) ? ($manifest['blocks'] ?? []) : [];
        $keyed = [];

        foreach ($blocks as $block) {
            if (is_array($block) && is_string($block['key'] ?? null)) {
                $keyed[$block['key']] = $block;
            }
        }

        return $keyed;
    }
}
