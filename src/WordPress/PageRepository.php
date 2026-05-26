<?php

declare(strict_types=1);

namespace Wordvel\WordPress;

final class PageRepository
{
    public function __construct(
        private readonly WordPressCodex $wordpress,
    ) {}

    public function findBySlug(string $slug): ?WordPressPage
    {
        $post = $this->wordpress->call('pageBySlug', ['slug' => $slug]);

        if (! is_array($post)) {
            return null;
        }

        return WordPressPage::fromCodex($post);
    }
}
