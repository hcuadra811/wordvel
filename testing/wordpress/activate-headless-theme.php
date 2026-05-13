<?php

declare(strict_types=1);

$_SERVER['HTTP_HOST'] = 'wordvel-wp.test';
$_SERVER['SERVER_NAME'] = 'wordvel-wp.test';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';

require_once __DIR__ . '/wp-load.php';

switch_theme('wordvel-headless');
flush_rewrite_rules();

echo "Activated wordvel-headless theme.\n";
