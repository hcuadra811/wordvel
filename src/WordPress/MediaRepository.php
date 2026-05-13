<?php

declare(strict_types=1);

namespace Wordvel\WordPress;

use Illuminate\Support\Facades\DB;

final class MediaRepository
{
    public function find(int $id): ?WordPressMedia
    {
        $connection = DB::connection((string) config('wordvel.connection', 'wordpress'));

        $attachment = $connection
            ->table('posts')
            ->where('ID', $id)
            ->where('post_type', 'attachment')
            ->first();

        if ($attachment === null) {
            return null;
        }

        $meta = $connection
            ->table('postmeta')
            ->where('post_id', $id)
            ->whereIn('meta_key', ['_wp_attachment_metadata', '_wp_attachment_image_alt'])
            ->pluck('meta_value', 'meta_key');

        $metadata = isset($meta['_wp_attachment_metadata'])
            ? @unserialize((string) $meta['_wp_attachment_metadata'])
            : [];

        return new WordPressMedia(
            id: (int) $attachment->ID,
            url: $attachment->guid ?: null,
            mimeType: $attachment->post_mime_type ?: null,
            title: $attachment->post_title ?: null,
            alt: $meta['_wp_attachment_image_alt'] ?? null,
            width: is_array($metadata) ? ($metadata['width'] ?? null) : null,
            height: is_array($metadata) ? ($metadata['height'] ?? null) : null,
        );
    }
}
