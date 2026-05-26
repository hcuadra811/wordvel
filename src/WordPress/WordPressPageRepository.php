<?php

declare(strict_types=1);

namespace Wordvel\WordPress;

use Wordvel\Data\WordPressPageData;

final class WordPressPageRepository
{
    public function __construct(
        private readonly GutenbergBlockParser $blocks,
        private readonly ManifestBlockRepository $manifest,
        private readonly WordPress $wordpress,
    ) {}

    /**
     * @template TPage of WordPressPageData
     * @param class-string<TPage> $pageClass
     * @param array<string, mixed> $additionalData
     * @return TPage|null
     */
    public function findBySlug(string $slug, string $pageClass = WordPressPageData::class, array $additionalData = []): ?WordPressPageData
    {
        $page = $this->wordpress->pages()->findBySlug($slug);

        if ($page === null) {
            return null;
        }

        /** @var TPage $resource */
        $resource = $pageClass::from([
            'id' => $page->id,
            'slug' => $page->slug,
            'title' => $page->title,
            'status' => $page->status,
            'blocks' => $this->blocks->parse($page->content, $this->manifest->keyed()),
            ...$additionalData,
        ]);

        return $resource;
    }
}
