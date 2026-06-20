<?php
/**
 * Bootstraps Laravel for the WordVel WordPress bridge.
 */

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;

function wordvel_laravel_path(): string
{
    return defined('WORDVEL_LARAVEL_PATH')
        ? WORDVEL_LARAVEL_PATH
        : dirname(__DIR__, 5) . '/laravel';
}
$laravelPath = defined('WORDVEL_LARAVEL_PATH')
    ? WORDVEL_LARAVEL_PATH
    : dirname(__DIR__, 5) . '/laravel';
$autoload = $laravelPath . '/vendor/autoload.php';
$bootstrap = $laravelPath . '/bootstrap/app.php';

if (! file_exists($autoload) || ! file_exists($bootstrap)) {
    return;
}

require_once $autoload;

$laravel = require $bootstrap;
$laravel->make(Kernel::class)->bootstrap();
