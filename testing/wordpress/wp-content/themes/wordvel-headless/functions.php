<?php

declare(strict_types=1);

add_action('after_setup_theme', static function (): void {
    add_theme_support('title-tag');
    add_theme_support('custom-logo');
    add_theme_support('post-thumbnails');

    register_nav_menus([
        'header' => __('Header', 'wordvel-headless'),
        'footer' => __('Footer', 'wordvel-headless'),
    ]);
});

add_action('template_redirect', static function (): void {
    if (is_admin() || wp_doing_ajax() || wp_is_json_request() || wp_is_serving_rest_request()) {
        return;
    }

    wp_safe_redirect(admin_url(), 302);
    exit;
});
