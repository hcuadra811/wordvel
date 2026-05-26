<?php

declare(strict_types=1);

namespace Wordvel\WordPress;

final class MediaRepository
{
    public function __construct(
        private readonly WordPressCodex $wordpress,
    ) {}

    public function find(int $id): ?WordPressMedia
    {
        $media = $this->wordpress->call('media', ['id' => $id]);

        if (! is_array($media)) {
            return null;
        }

        return new WordPressMedia(
            id: (int) $media['id'],
            url: $media['url'] ?? null,
            mimeType: $media['mime_type'] ?? null,
            title: $media['title'] ?? null,
            alt: $media['alt'] ?? null,
            width: isset($media['width']) ? (int) $media['width'] : null,
            height: isset($media['height']) ? (int) $media['height'] : null,
        );
    }
}
