<?php
/**
 * Plugin Name: WordVel Loader
 * Description: Loads the WordVel bridge automatically in the local test WordPress install.
 */

declare(strict_types=1);

$plugin = dirname(__DIR__) . '/plugins/wordvel/wordvel.php';

if (file_exists($plugin)) {
    require_once $plugin;
}
