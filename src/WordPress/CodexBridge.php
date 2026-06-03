<?php

declare(strict_types=1);

$loader = $argv[1] ?? null;

try {
    if (! is_string($loader) || ! is_file($loader)) {
        throw new RuntimeException('WordPress loader was not found.');
    }

    $request = json_decode((string) stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);

    require_once $loader;

    $action = (string) ($request['action'] ?? '');
    $payload = is_array($request['payload'] ?? null) ? $request['payload'] : [];

    echo json_encode([
        'ok' => true,
        'data' => match ($action) {
            'pageBySlug' => wordvel_codex_page_by_slug((string) ($payload['slug'] ?? '')),
            'media' => wordvel_codex_media((int) ($payload['id'] ?? 0)),
            'importMedia' => wordvel_codex_import_media(
                (string) ($payload['path'] ?? ''),
                isset($payload['title']) ? (string) $payload['title'] : null,
                isset($payload['alt']) ? (string) $payload['alt'] : null,
            ),
            'option' => wordvel_codex_option((string) ($payload['key'] ?? ''), $payload['default'] ?? null),
            'menuByLocation' => wordvel_codex_menu_by_location(
                (string) ($payload['location'] ?? ''),
                (string) ($payload['theme'] ?? 'wordvel-headless'),
            ),
            'siteIdentity' => wordvel_codex_site_identity(),
            'setOption' => wordvel_codex_set_option(
                (string) ($payload['key'] ?? ''),
                $payload['value'] ?? null,
            ),
            'setThemeMod' => wordvel_codex_set_theme_mod(
                (string) ($payload['key'] ?? ''),
                $payload['value'] ?? null,
            ),
            default => throw new InvalidArgumentException("Unknown WordPress Codex action [{$action}]."),
        },
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    echo json_encode([
        'ok' => false,
        'error' => $exception->getMessage(),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    exit(1);
}

function wordvel_codex_page_by_slug(string $slug): ?array
{
    $post = get_page_by_path($slug, OBJECT, 'page');

    if ($post === null || ! in_array((string) $post->post_status, ['publish', 'draft'], true)) {
        return null;
    }

    return [
        'id' => (int) $post->ID,
        'slug' => (string) $post->post_name,
        'title' => (string) $post->post_title,
        'status' => (string) $post->post_status,
        'content' => (string) $post->post_content,
    ];
}

function wordvel_codex_media(int $id): ?array
{
    $attachment = get_post($id);

    if ($attachment === null || $attachment->post_type !== 'attachment') {
        return null;
    }

    $metadata = wp_get_attachment_metadata($id);

    return [
        'id' => $id,
        'url' => wp_get_attachment_url($id) ?: null,
        'mime_type' => get_post_mime_type($id) ?: null,
        'title' => get_the_title($id) ?: null,
        'alt' => get_post_meta($id, '_wp_attachment_image_alt', true) ?: null,
        'width' => is_array($metadata) ? ($metadata['width'] ?? null) : null,
        'height' => is_array($metadata) ? ($metadata['height'] ?? null) : null,
        'duration_seconds' => is_array($metadata) && isset($metadata['length']) ? (int) $metadata['length'] : null,
    ];
}

function wordvel_codex_import_media(string $path, ?string $title = null, ?string $alt = null): ?array
{
    if ($path === '' || ! is_file($path) || ! is_readable($path)) {
        throw new InvalidArgumentException('Media source file was not found or is not readable.');
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $hash = hash_file('sha256', $path);
    $existing = get_posts([
        'post_type' => 'attachment',
        'post_status' => 'inherit',
        'posts_per_page' => 1,
        'fields' => 'ids',
        'meta_query' => [[
            'key' => '_wordvel_source_hash',
            'value' => $hash,
        ]],
    ]);

    if (is_array($existing) && isset($existing[0])) {
        return wordvel_codex_media((int) $existing[0]);
    }

    $temporaryPath = wp_tempnam(basename($path));

    if (! is_string($temporaryPath) || ! copy($path, $temporaryPath)) {
        throw new RuntimeException('Unable to prepare media source file for WordPress import.');
    }

    $file = [
        'name' => basename($path),
        'type' => mime_content_type($path) ?: 'application/octet-stream',
        'tmp_name' => $temporaryPath,
        'error' => 0,
        'size' => filesize($path) ?: 0,
    ];

    $attachmentId = media_handle_sideload($file, 0, $title);

    if (is_wp_error($attachmentId)) {
        throw new RuntimeException($attachmentId->get_error_message());
    }

    update_post_meta((int) $attachmentId, '_wordvel_source_hash', $hash);

    if ($alt !== null && $alt !== '') {
        update_post_meta((int) $attachmentId, '_wp_attachment_image_alt', $alt);
    }

    return wordvel_codex_media((int) $attachmentId);
}

function wordvel_codex_option(string $key, mixed $default = null): mixed
{
    return get_option($key, $default);
}

function wordvel_codex_menu_by_location(string $location, string $theme): array
{
    $themeMods = get_option('theme_mods_' . $theme, []);
    $locations = is_array($themeMods) && is_array($themeMods['nav_menu_locations'] ?? null)
        ? $themeMods['nav_menu_locations']
        : get_nav_menu_locations();
    $menuId = $locations[$location] ?? null;

    if (! is_numeric($menuId)) {
        return [
            'location' => $location,
            'name' => null,
            'items' => [],
        ];
    }

    $menu = wp_get_nav_menu_object((int) $menuId);
    $items = array_map(static fn (object $item): array => [
        'id' => (int) $item->ID,
        'parent_id' => (int) ($item->menu_item_parent ?? 0),
        'title' => html_entity_decode((string) $item->post_title, ENT_QUOTES),
        'url' => (string) ($item->url ?? '#'),
        'target' => ($item->target ?? '') !== '' ? (string) $item->target : null,
        'order' => (int) $item->menu_order,
        'type' => (string) ($item->type ?? 'custom'),
        'object' => (string) ($item->object ?? 'custom'),
        'object_id' => (int) ($item->object_id ?? 0),
    ], wp_get_nav_menu_items((int) $menuId) ?: []);

    return [
        'location' => $location,
        'name' => $menu !== false ? (string) $menu->name : null,
        'items' => $items,
    ];
}
