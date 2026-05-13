<?php

declare(strict_types=1);

define('WP_INSTALLING', true);

$_SERVER['HTTP_HOST'] = '127.0.0.1:8088';
$_SERVER['SERVER_NAME'] = '127.0.0.1';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';

require_once __DIR__ . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/upgrade.php';

if (is_blog_installed()) {
    echo "WordPress is already installed.\n";

    return;
}

$result = wp_install(
    blog_title: 'WordVel Testing',
    user_name: 'admin',
    user_email: 'admin@example.test',
    is_public: false,
    deprecated: '',
    user_password: 'password'
);

$userId = is_array($result) ? ($result['user_id'] ?? 'unknown') : $result;

echo "Installed WordPress with admin user {$userId}.\n";
