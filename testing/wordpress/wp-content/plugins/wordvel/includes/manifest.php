<?php
/**
 * Manifest loading helpers for the WordVel WordPress bridge.
 */

declare(strict_types=1);

function wordvel_manifest(): ?array
{
    $manifestPath = wordvel_laravel_path() . '/storage/wordvel/manifest.json';

    if (! file_exists($manifestPath)) {
        return null;
    }

    $manifest = json_decode((string) file_get_contents($manifestPath), true);

    return is_array($manifest) ? $manifest : null;
}
