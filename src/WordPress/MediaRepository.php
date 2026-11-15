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

        return $this->mediaFromPayload($media);
    }

    public function importFile(string $path, ?string $title = null, ?string $alt = null): ?WordPressMedia
    {
        $media = $this->wordpress->call('importMedia', [
            'path' => $path,
            'title' => $title,
            'alt' => $alt,
        ]);

        return $this->mediaFromPayload($media);
    }

    /**
     * Permanently deletes the attachment and its files from the media library.
     * Returns false when the id is not a media library attachment or WordPress
     * could not delete it.
     *
     * importFile() returns the existing attachment when a file with the same
     * content was imported before, so several records can share one
     * attachment. Check that nothing else references it before deleting.
     */
    public function delete(int $id): bool
    {
        return $this->wordpress->call('deleteMedia', ['id' => $id]) === true;
    }

    private function mediaFromPayload(mixed $media): ?WordPressMedia
    {
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
            durationSeconds: isset($media['duration_seconds']) ? (int) $media['duration_seconds'] : null,
        );
    }
}
