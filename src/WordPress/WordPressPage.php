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

    public static function fromDatabaseRecord(object $post): self
    {
        return new self(
            id: (int) $post->ID,
            slug: (string) $post->post_name,
            title: (string) $post->post_title,
            status: (string) $post->post_status,
            content: (string) $post->post_content,
        );
    }
}
