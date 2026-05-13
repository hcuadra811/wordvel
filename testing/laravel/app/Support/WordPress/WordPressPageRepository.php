<?php

declare(strict_types=1);

namespace App\Support\WordPress;

use App\Data\PageResource;
use Wordvel\WordPress\WordPress;

final class WordPressPageRepository
{
    public function __construct(
        private readonly GutenbergBlockParser $blocks,
        private readonly WordPress $wordpress,
    ) {}

    public function findBySlug(string $slug): ?PageResource
    {
        $page = $this->wordpress->pages()->findBySlug($slug);

        if ($page === null) {
            return null;
        }

        $manifest = json_decode((string) file_get_contents(storage_path('wordvel/manifest.json')), true);
        $schemas = collect($manifest['blocks'] ?? [])->keyBy('key')->all();

        return new PageResource(
            id: $page->id,
            slug: $page->slug,
            title: $page->title,
            status: $page->status,
            blocks: $this->blocks->parse($page->content, $schemas),
        );
    }
}
