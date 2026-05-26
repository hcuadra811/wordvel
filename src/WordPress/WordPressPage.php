<?php

declare(strict_types=1);

namespace Wordvel\WordPress;

final class WordPressPage
{
    public function __construct(
        public readonly int $id,
        public readonly string $slug,
        public readonly string $title,
        public readonly string $status,
        public readonly string $content,
    ) {}

    /**
     * @param array<string, mixed> $post
     */
    public static function fromCodex(array $post): self
    {
        return new self(
            id: (int) $post['id'],
            slug: (string) $post['slug'],
            title: (string) $post['title'],
            status: (string) $post['status'],
            content: (string) $post['content'],
        );
    }
}
