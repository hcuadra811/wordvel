<?php

declare(strict_types=1);

/*
 * Stand-in for WordPress's wp-load.php, loaded by the WordPress Codex
 * subprocess in tests. Post 10 is an attachment, 20 is a page, and 30 is an
 * attachment WordPress fails to delete. Each wp_delete_attachment call is
 * appended to storage/framework/testing/codex-deletions.log.
 */

function get_post(int $id): ?object
{
    $types = [10 => 'attachment', 20 => 'page', 30 => 'attachment'];

    return isset($types[$id]) ? (object) ['ID' => $id, 'post_type' => $types[$id]] : null;
}

function wp_delete_attachment(int $id, bool $forceDelete = false): object|false|null
{
    file_put_contents(
        dirname(__DIR__, 3) . '/storage/framework/testing/codex-deletions.log',
        json_encode(['id' => $id, 'force' => $forceDelete]) . "\n",
        FILE_APPEND,
    );

    return $id === 30 ? false : (object) ['ID' => $id];
}
