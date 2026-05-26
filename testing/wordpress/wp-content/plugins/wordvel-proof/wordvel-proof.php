<?php
/**
 * Plugin Name: WordVel Proof
 * Description: Minimal proof that WordPress can dispatch a REST request into app code through WordVel.
 * Version: 0.0.1
 */

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Wordvel\Runtime\Application as WordvelApplication;

$laravelPath = defined('WORDVEL_LARAVEL_PATH')
    ? WORDVEL_LARAVEL_PATH
    : dirname(__DIR__, 4) . '/laravel';
$autoload = $laravelPath . '/vendor/autoload.php';
$bootstrap = $laravelPath . '/bootstrap/app.php';

if (! file_exists($autoload) || ! file_exists($bootstrap)) {
    return;
}

require_once $autoload;

$laravel = require $bootstrap;
$laravel->make(Kernel::class)->bootstrap();

add_action('admin_menu', static function (): void {
    add_menu_page(
        'Regions',
        'Regions',
        'manage_options',
        'wordvel-regions',
        'wordvel_regions_index_page',
        'dashicons-layout',
        58,
    );

    $manifest = wordvel_manifest();
    $regions = wordvel_sorted_regions(is_array($manifest) ? ($manifest['regions'] ?? []) : []);
    $themeOptions = is_array($manifest) ? ($manifest['theme_options'] ?? null) : null;

    if (is_array($themeOptions)) {
        add_submenu_page(
            'wordvel-regions',
            (string) ($themeOptions['name'] ?? 'Theme Options'),
            (string) ($themeOptions['name'] ?? 'Theme Options'),
            'manage_options',
            'wordvel-theme-options',
            'wordvel_theme_options_page',
        );
    }

    foreach ($regions as $region) {
        if (! is_array($region)) {
            continue;
        }

        $regionKey = (string) ($region['key'] ?? '');

        if ($regionKey === '') {
            continue;
        }

        add_submenu_page(
            'wordvel-regions',
            (string) ($region['name'] ?? ucfirst($regionKey)),
            (string) ($region['name'] ?? ucfirst($regionKey)),
            'manage_options',
            'wordvel-region-' . $regionKey,
            static fn () => wordvel_region_page($regionKey),
        );
    }

});

add_action('init', static function (): void {
    wp_register_script(
        'wordvel-editor-blocks',
        plugins_url('editor-blocks.js', __FILE__),
        ['wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-data', 'wp-plugins'],
        (string) filemtime(__DIR__ . '/editor-blocks.js'),
        true,
    );

    add_filter('block_categories_all', static function (array $categories): array {
        array_unshift($categories, [
            'slug' => 'wordvel',
            'title' => 'WordVel',
            'icon' => null,
        ]);

        return $categories;
    });

    if (! is_admin()) {
        foreach (wordvel_block_schemas() as $block) {
            wordvel_register_editor_block($block);
        }
    }
});

add_action('admin_enqueue_scripts', static function (): void {
    global $wp_scripts;

    if (! $wp_scripts instanceof WP_Scripts || ! isset($wp_scripts->registered['wp-edit-post'])) {
        return;
    }

    if (! in_array('wordvel-editor-blocks', $wp_scripts->registered['wp-edit-post']->deps, true)) {
        $wp_scripts->registered['wp-edit-post']->deps[] = 'wordvel-editor-blocks';
    }
}, 20);

add_action('enqueue_block_editor_assets', static function (): void {
    foreach (wordvel_editor_style_urls() as $index => $styleUrl) {
        wp_enqueue_style(
            'wordvel-editor-style-' . $index,
            $styleUrl,
            [],
            null,
        );
    }

    wp_register_style('wordvel-editor-inline-style', false);
    wp_enqueue_style('wordvel-editor-inline-style');
    wp_add_inline_style('wordvel-editor-inline-style', wordvel_editor_css());

    wp_add_inline_script(
        'wordvel-editor-blocks',
        'window.wordvelBlocks = ' . wp_json_encode(wordvel_block_schemas()) . ';' . "\n" .
        'window.wordvelEditorPreviews = ' . wp_json_encode(wordvel_editor_preview_templates()) . ';',
        'before',
    );
});

add_filter('block_editor_settings_all', static function (array $settings): array {
    $css = wordvel_editor_css();

    if ($css === '') {
        return $settings;
    }

    $settings['styles'] = array_merge($settings['styles'] ?? [], [
        [
            'css' => $css,
        ],
    ]);

    return $settings;
});

function wordvel_editor_style_urls(): array
{
    $manifest = wordvel_manifest();
    $styles = is_array($manifest) ? ($manifest['editor']['styles'] ?? []) : [];

    if (! is_array($styles)) {
        return [];
    }

    return array_values(array_filter($styles, static fn (mixed $style): bool => is_string($style) && $style !== ''));
}

function wordvel_editor_css(): string
{
    $css = '';

    foreach (wordvel_editor_style_urls() as $styleUrl) {
        $response = wp_remote_get($styleUrl, [
            'timeout' => 2,
        ]);

        if (is_wp_error($response)) {
            continue;
        }

        $body = wp_remote_retrieve_body($response);

        if (is_string($body) && $body !== '') {
            $css .= "\n" . $body;
        }
    }

    try {
        /** @var \Wordvel\Editor\EditorPreviewRepository $previews */
        $previews = app(\Wordvel\Editor\EditorPreviewRepository::class);
        $syncedCss = $previews->css();

        if ($syncedCss !== '') {
            $css .= "\n" . $syncedCss;
        }
    } catch (Throwable) {
        // Keep the editor usable even if Laravel is unavailable during WordPress boot.
    }

    if ($css !== '') {
        $css .= "\n" . str_replace(
            ['body.theme-night', 'body.theme-dark'],
            ['.editor-styles-wrapper.wordvel-editor-mode-dark', '.editor-styles-wrapper.wordvel-editor-mode-dark'],
            $css,
        );

        return $css . "\n" . wordvel_editor_layout_overrides();
    }

    if ($css === '' && is_file(__DIR__ . '/editor-style.css')) {
        $css = (string) file_get_contents(__DIR__ . '/editor-style.css');
        $css .= "\n" . str_replace(
            ['body.theme-night', 'body.theme-dark'],
            ['.editor-styles-wrapper.wordvel-editor-mode-dark', '.editor-styles-wrapper.wordvel-editor-mode-dark'],
            $css,
        );

        return $css . "\n" . wordvel_editor_layout_overrides();
    }

    return $css;
}

function wordvel_editor_layout_overrides(): string
{
    return <<<'CSS'
.block-editor-block-list__layout .wp-block[data-type^="wordvel/"] {
    min-width: 0 !important;
    max-width: none !important;
}

.block-editor-block-list__layout .wp-block[data-type^="wordvel/"]:not([data-align]),
.editor-styles-wrapper .wordvel-block {
    max-width: none !important;
    width: 100% !important;
}

.editor-styles-wrapper .wordvel-block {
    margin-left: 0 !important;
    margin-right: 0 !important;
}

.editor-styles-wrapper .wordvel-block h1,
.editor-styles-wrapper .wordvel-block h2,
.editor-styles-wrapper .wordvel-block h3,
.editor-styles-wrapper .wordvel-block p,
.editor-styles-wrapper .wordvel-block a,
.editor-styles-wrapper .wordvel-block span,
.editor-styles-wrapper .wordvel-block strong {
    overflow-wrap: normal !important;
    word-break: normal !important;
}

.editor-styles-wrapper .wordvel-block .block-editor-rich-text__editable,
.editor-styles-wrapper .wordvel-block :has(> .block-editor-rich-text__editable) {
    pointer-events: auto !important;
}

.editor-styles-wrapper .wordvel-block .block-editor-rich-text__editable {
    cursor: text;
    user-select: text;
}

.editor-styles-wrapper .wordvel-block :is(a, span, figure):has(> img[src=""]) {
    display: none !important;
}

.editor-styles-wrapper .wordvel-block button[aria-label$="﻿"],
.editor-styles-wrapper .wordvel-block button[aria-label$=" "] {
    display: none !important;
}

.wordvel-repeater-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    margin: 0 0 8px;
}

.wordvel-repeater-heading strong {
    min-width: 0;
    font-size: 13px;
    line-height: 1.2;
}

.wordvel-repeater-add-button.components-button {
    width: 28px;
    min-width: 28px;
    height: 28px;
    margin-left: auto;
    padding: 0;
    border-radius: 2px;
    font-size: 20px;
    line-height: 1;
}

.wordvel-media-control {
    display: grid;
    gap: 8px;
}

.wordvel-media-label {
    margin: 0;
    color: #1e1e1e;
    font-size: 11px;
    font-weight: 500;
    line-height: 1.4;
    text-transform: uppercase;
}

.wordvel-media-preview {
    display: block;
    width: 100%;
    height: auto;
    margin: 0;
}

.wordvel-media-button.components-button {
    width: fit-content;
    min-height: 32px;
    padding: 0 10px;
    font-size: 13px;
    line-height: 1;
}

.media-modal,
.media-modal * {
    letter-spacing: 0 !important;
}

.media-modal h1,
.media-modal h2,
.media-modal h3,
.media-modal p,
.media-modal button,
.media-modal input,
.media-modal select,
.media-modal textarea {
    color: revert;
    font: revert;
    line-height: revert;
    text-transform: none;
}

.media-modal h1,
.media-modal h2 {
    font-size: 24px !important;
    font-weight: 600 !important;
    line-height: 1.25 !important;
}

.media-modal img {
    display: revert;
    max-width: revert;
}

CSS;
}

function wordvel_editor_preview_templates(): array
{
    try {
        /** @var \Wordvel\Editor\EditorPreviewRepository $previews */
        $previews = app(\Wordvel\Editor\EditorPreviewRepository::class);
        $preview = $previews->preview();
    } catch (Throwable) {
        return [];
    }

    $blocks = $preview['blocks'] ?? [];

    return is_array($blocks) ? $blocks : [];
}

add_action('template_redirect', static function (): void {
    $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);

    if ($path !== '/wordvel-docs') {
        return;
    }

    status_header(200);
    nocache_headers();
    header('Content-Type: text/html; charset=' . get_bloginfo('charset'));

    echo wordvel_docs_html();
    exit;
}, 0);

add_action('rest_api_init', static function (): void {
    $runtime = app(WordvelApplication::class);
    $manifest = wordvel_manifest();

    register_rest_route('wordvel/v1', '/site', [
        'methods' => 'GET',
        'callback' => static fn (): WP_REST_Response => new WP_REST_Response(wordvel_site_payload()),
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('wordvel/v1', '/docs', [
        'methods' => 'GET',
        'callback' => static fn (): WP_REST_Response => new WP_REST_Response(wordvel_docs_payload()),
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('wordvel/v1', '/pages/(?P<slug>[a-zA-Z0-9_-]+)', [
        'methods' => 'GET',
        'callback' => static function (WP_REST_Request $request): WP_REST_Response {
            $page = wordvel_page_payload((string) $request->get_param('slug'));

            if ($page === null) {
                return new WP_REST_Response([
                    'status' => false,
                    'error' => [
                        'message' => 'Page schema not found.',
                    ],
                ], 404);
            }

            return new WP_REST_Response([
                'status' => true,
                'data' => $page,
            ]);
        },
        'permission_callback' => '__return_true',
    ]);

    if (! is_array($manifest)) {
        return;
    }

    $namespace = trim((string) ($manifest['namespace'] ?? 'wordvel'), '/');
    $prefix = trim((string) ($manifest['prefix'] ?? 'v1'), '/');

    foreach ($manifest['routes'] ?? [] as $route) {
        if (! is_array($route)) {
            continue;
        }

        register_rest_route($namespace . '/' . $prefix, (string) $route['path'], [
            'methods' => $route['methods'] ?? ['GET'],
            'callback' => static function (WP_REST_Request $request) use ($runtime, $route): WP_REST_Response {
                return $runtime->dispatch([
                    'controller' => (string) $route['controller'],
                    'method' => (string) $route['method'],
                    'request_dto' => $route['request_dto'] ?? null,
                    'response_dto' => $route['response_dto'] ?? null,
                    'wp_request' => $request,
                ]);
            },
            'permission_callback' => static function () use ($route): bool {
                $capability = $route['capability'] ?? null;

                if ($capability === null || $capability === '') {
                    return true;
                }

                return current_user_can((string) $capability);
            },
        ]);
    }
});

function wordvel_site_payload(): array
{
    return [
        'name' => get_bloginfo('name'),
        'description' => get_bloginfo('description'),
        'url' => home_url('/'),
        'logo' => wordvel_logo_payload(),
        'menus' => [
            'header' => wordvel_menu_payload('header'),
            'footer' => wordvel_menu_payload('footer'),
        ],
        'regions' => wordvel_regions_payload(),
        'theme_options' => [
            'schema' => wordvel_theme_options_schema(),
            'values' => wordvel_theme_options_values(),
        ],
    ];
}

function wordvel_docs_payload(): array
{
    $manifest = wordvel_manifest();
    $namespace = is_array($manifest) ? (string) ($manifest['namespace'] ?? 'wordvel') : 'wordvel';
    $prefix = is_array($manifest) ? (string) ($manifest['prefix'] ?? 'v1') : 'v1';
    $base = home_url('/wp-json');

    return [
        'openapi' => '3.1.0',
        'info' => [
            'title' => 'WordVel Testing API',
            'version' => '0.0.1',
            'description' => 'Current proof API. This will be generated from Laravel Data DTOs and route annotations.',
        ],
        'servers' => [
            [
                'url' => $base,
            ],
        ],
        'paths' => [
            '/' . $namespace . '/' . $prefix . '/site' => [
                'get' => [
                    'summary' => 'Get site chrome, navigation, and structured regions',
                    'description' => 'Returns WordPress site identity, logo, registered menus, and WordVel code-defined regions with saved content values.',
                    'tags' => ['WordVel'],
                    'responses' => [
                        '200' => [
                            'description' => 'Site chrome payload.',
                        ],
                    ],
                ],
            ],
            '/' . $namespace . '/' . $prefix . '/pages/{slug}' => [
                'get' => [
                    'summary' => 'Get a page by slug',
                    'description' => 'Returns a WordVel page resource. The page schema and blocks are defined in Laravel code; WordPress stores content values.',
                    'tags' => ['Pages'],
                    'parameters' => [
                        [
                            'name' => 'slug',
                            'in' => 'path',
                            'required' => true,
                            'schema' => [
            'type' => 'string',
                            ],
                            'description' => 'WordPress page slug, for example home.',
                        ],
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'Page resource.',
                        ],
                        '404' => [
                            'description' => 'Page schema not found.',
                        ],
                    ],
                ],
            ],
            '/' . $namespace . '/' . $prefix . '/docs' => [
                'get' => [
                    'summary' => 'Get API documentation',
                    'description' => 'Returns the current API documentation payload.',
                    'tags' => ['WordVel'],
                    'responses' => [
                        '200' => [
                            'description' => 'OpenAPI documentation payload.',
                        ],
                    ],
                ],
            ],
        ],
        'x-wordvel' => [
            'navigation' => [
                'source' => '/' . $namespace . '/' . $prefix . '/site',
                'field' => 'menus',
            ],
            'regions' => [
                'source' => '/' . $namespace . '/' . $prefix . '/site',
                'field' => 'regions',
            ],
            'manifest_routes' => is_array($manifest) ? ($manifest['routes'] ?? []) : [],
            'region_schema' => is_array($manifest) ? ($manifest['regions'] ?? []) : [],
            'block_schema' => is_array($manifest) ? ($manifest['blocks'] ?? []) : [],
            'theme_options_schema' => is_array($manifest) ? ($manifest['theme_options'] ?? null) : null,
        ],
    ];
}

function wordvel_docs_html(): string
{
    $specUrl = esc_url(home_url('/wp-json/wordvel/v1/docs'));

    return <<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>WordVel API Docs</title>
    <style>
        body {
            margin: 0;
            padding: 0;
        }
    </style>
</head>
<body>
    <redoc spec-url="{$specUrl}"></redoc>
    <script src="https://cdn.redoc.ly/redoc/latest/bundles/redoc.standalone.js"></script>
</body>
</html>
HTML;
}

function wordvel_logo_payload(): ?array
{
    $logoId = get_theme_mod('custom_logo');

    if (! is_numeric($logoId)) {
        return null;
    }

    $image = wp_get_attachment_image_src((int) $logoId, 'full');

    if ($image === false) {
        return null;
    }

    return [
        'id' => (int) $logoId,
        'url' => $image[0],
        'width' => $image[1],
        'height' => $image[2],
        'alt' => get_post_meta((int) $logoId, '_wp_attachment_image_alt', true) ?: null,
    ];
}

function wordvel_menu_payload(string $location): array
{
    $locations = get_nav_menu_locations();
    $menuId = $locations[$location] ?? null;

    if ($menuId === null) {
        return [
            'location' => $location,
            'name' => null,
            'items' => [],
        ];
    }

    $menu = wp_get_nav_menu_object($menuId);
    $items = wp_get_nav_menu_items($menuId) ?: [];

    return [
        'location' => $location,
        'name' => $menu?->name,
        'items' => array_map(static fn (WP_Post $item): array => [
            'id' => $item->ID,
            'parent_id' => (int) $item->menu_item_parent,
            'title' => html_entity_decode($item->title, ENT_QUOTES, get_bloginfo('charset')),
            'url' => $item->url,
            'target' => $item->target ?: null,
            'order' => (int) $item->menu_order,
            'type' => $item->type,
            'object' => $item->object,
            'object_id' => (int) $item->object_id,
        ], $items),
    ];
}

function wordvel_regions_index_page(): void
{
    if (! current_user_can('manage_options')) {
        wp_die('You do not have permission to manage WordVel regions.');
    }

    $manifest = wordvel_manifest();
    $schemas = wordvel_sorted_regions(is_array($manifest) ? ($manifest['regions'] ?? []) : []);

    echo '<div class="wrap">';
    echo '<h1>Regions</h1>';

    if ($schemas === []) {
        echo '<p>No WordVel regions are defined in code yet.</p>';
        echo '</div>';

        return;
    }

    echo '<p>Regions are defined in code. Choose a region to edit its content values.</p>';
    echo '<ul>';

    foreach ($schemas as $region) {
        if (! is_array($region)) {
            continue;
        }

        $regionKey = (string) ($region['key'] ?? '');

        if ($regionKey === '') {
            continue;
        }

        printf(
            '<li><a href="%s">%s</a></li>',
            esc_url(admin_url('admin.php?page=wordvel-region-' . $regionKey)),
            esc_html((string) ($region['name'] ?? ucfirst($regionKey))),
        );
    }

    echo '</ul>';
    echo '</div>';
}

function wordvel_theme_options_page(): void
{
    if (! current_user_can('manage_options')) {
        wp_die('You do not have permission to manage WordVel theme options.');
    }

    $schema = wordvel_theme_options_schema();
    $notice = null;

    if ($schema === null) {
        echo '<div class="wrap">';
        echo '<h1>Theme Options</h1>';
        echo '<p>No theme options schema is defined in Laravel.</p>';
        echo '</div>';

        return;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        check_admin_referer('wordvel_theme_options');

        update_option(
            'wordvel_theme_options',
            wordvel_sanitize_theme_options_value($schema, $_POST['theme_options'] ?? []),
            false,
        );

        $notice = [
            'type' => 'success',
            'message' => 'Theme options saved.',
        ];
    }

    $values = wordvel_theme_options_values();

    echo '<div class="wrap">';
    printf('<h1>%s</h1>', esc_html((string) ($schema['name'] ?? 'Theme Options')));
    echo '<p>These options are defined in Laravel code, stored by WordPress, and exposed through <code>/api/v1/site</code>.</p>';

    if ($notice !== null) {
        printf(
            '<div class="notice notice-%s"><p>%s</p></div>',
            esc_attr($notice['type']),
            esc_html($notice['message']),
        );
    }

    echo '<form method="post">';
    wp_nonce_field('wordvel_theme_options');
    echo '<div style="display: grid; gap: 16px; max-width: 760px;">';

    foreach (($schema['groups'] ?? []) as $group) {
        if (is_array($group)) {
            wordvel_render_theme_option_group($group, $values);
        }
    }

    echo '</div>';
    submit_button('Save Theme Options');
    echo '</form>';
    echo '</div>';
}

function wordvel_render_theme_option_group(array $group, array $values): void
{
    $groupKey = (string) ($group['key'] ?? '');

    if ($groupKey === '') {
        return;
    }

    $groupValues = is_array($values[$groupKey] ?? null) ? $values[$groupKey] : [];

    echo '<div style="background: #fff; border: 1px solid #c3c4c7; padding: 16px;">';
    printf('<h2 style="margin-top: 0;">%s</h2>', esc_html((string) ($group['label'] ?? ucfirst($groupKey))));

    foreach (($group['fields'] ?? []) as $field) {
        if (is_array($field)) {
            wordvel_render_theme_option_field($groupKey, $field, $groupValues);
        }
    }

    echo '</div>';
}

function wordvel_render_theme_option_field(string $groupKey, array $field, array $groupValues): void
{
    $fieldKey = (string) ($field['key'] ?? '');

    if ($fieldKey === '') {
        return;
    }

    $name = sprintf('theme_options[%s][%s]', $groupKey, $fieldKey);
    $value = (string) ($groupValues[$fieldKey] ?? ($field['default'] ?? ''));
    $type = (string) ($field['type'] ?? 'text');
    $label = (string) ($field['label'] ?? ucfirst($fieldKey));

    printf('<p><label><strong>%s</strong><br>', esc_html($label));

    if ($type === 'select') {
        echo '<select name="' . esc_attr($name) . '">';

        foreach (($field['options'] ?? []) as $optionValue => $optionLabel) {
            printf(
                '<option value="%s"%s>%s</option>',
                esc_attr((string) $optionValue),
                selected($value, (string) $optionValue, false),
                esc_html((string) $optionLabel),
            );
        }

        echo '</select>';
    } else {
        printf(
            '<input type="%s" name="%s" value="%s" class="regular-text">',
            esc_attr($type === 'url' ? 'url' : 'text'),
            esc_attr($name),
            esc_attr($value),
        );
    }

    echo '</label></p>';
}

function wordvel_region_page(string $regionKey): void
{
    if (! current_user_can('manage_options')) {
        wp_die('You do not have permission to manage WordVel regions.');
    }

    $region = wordvel_region_schema($regionKey);
    $notice = null;

    if ($region === null) {
        echo '<div class="wrap">';
        echo '<h1>Region Not Found</h1>';
        echo '<p>This region is not defined in code.</p>';
        echo '</div>';

        return;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        check_admin_referer('wordvel_region_' . $regionKey);

        $allValues = wordvel_region_values();
        $allValues[$regionKey] = wordvel_sanitize_region_value($region, $_POST['region'] ?? []);
        update_option('wordvel_region_values', $allValues, false);
        $notice = [
            'type' => 'success',
            'message' => 'Region saved.',
        ];
    }

    $values = wordvel_region_values();

    echo '<div class="wrap">';
    printf('<h1>%s</h1>', esc_html((string) ($region['name'] ?? ucfirst($regionKey))));

    if ($notice !== null) {
        printf(
            '<div class="notice notice-%s"><p>%s</p></div>',
            esc_attr($notice['type']),
            esc_html($notice['message']),
        );
    }

    echo '<form method="post">';
    wp_nonce_field('wordvel_region_' . $regionKey);
    wordvel_render_region_form($region, $values);

    submit_button('Save Region');
    echo '</form>';
    echo '</div>';
}

function wordvel_regions_payload(): array
{
    $manifest = wordvel_manifest();
    $schemas = wordvel_sorted_regions(is_array($manifest) ? ($manifest['regions'] ?? []) : []);
    $values = wordvel_region_values();
    $regions = [];

    foreach ($schemas as $region) {
        if (! is_array($region)) {
            continue;
        }

        $regionKey = (string) ($region['key'] ?? '');

        if ($regionKey === '') {
            continue;
        }

        $regions[$regionKey] = [
            'id' => $regionKey,
            'name' => (string) ($region['name'] ?? ucfirst($regionKey)),
            'blocks' => wordvel_region_blocks_payload($region, $values[$regionKey] ?? []),
        ];
    }

    return $regions;
}

function wordvel_region_blocks_payload(array $region, mixed $regionValues): array
{
    $blocks = [];
    $regionValues = is_array($regionValues) ? $regionValues : [];

    foreach (($region['blocks'] ?? []) as $block) {
        if (! is_array($block)) {
            continue;
        }

        $blockKey = (string) ($block['key'] ?? '');

        if ($blockKey === '') {
            continue;
        }

        $blocks[] = [
            'id' => $blockKey,
            'type' => (string) ($block['type'] ?? ''),
            'label' => (string) ($block['label'] ?? ucfirst($blockKey)),
            'data' => wordvel_block_data_payload($block, $regionValues[$blockKey] ?? []),
        ];
    }

    return $blocks;
}

function wordvel_block_data_payload(array $block, mixed $blockValues): array
{
    $blockValues = is_array($blockValues) ? $blockValues : [];
    $data = [];

    foreach (($block['fields'] ?? []) as $field) {
        if (! is_array($field)) {
            continue;
        }

        $fieldKey = (string) ($field['key'] ?? '');

        if ($fieldKey === '') {
            continue;
        }

        $data[$fieldKey] = wordvel_field_value_payload(
            $field,
            $blockValues[$fieldKey] ?? ($field['default'] ?? null),
        );
    }

    if (($block['type'] ?? null) === 'logo') {
        $data['source'] = 'site.logo';
    }

    return $data;
}

function wordvel_field_value_payload(array $field, mixed $value): mixed
{
    return match ((string) ($field['type'] ?? 'text')) {
        'number' => is_numeric($value) ? $value + 0 : null,
        'boolean' => $value === true || $value === '1' || $value === 1,
        'image', 'media' => wordvel_media_payload($value),
        default => $value,
    };
}

function wordvel_media_payload(mixed $value): ?array
{
    if (! is_numeric($value) || (int) $value <= 0) {
        return null;
    }

    $id = (int) $value;
    $url = wp_get_attachment_url($id);

    if ($url === false) {
        return null;
    }

    $metadata = wp_get_attachment_metadata($id);

    return [
        'id' => $id,
        'url' => $url,
        'mime_type' => get_post_mime_type($id) ?: null,
        'title' => get_the_title($id),
        'alt' => get_post_meta($id, '_wp_attachment_image_alt', true) ?: null,
        'width' => is_array($metadata) ? ($metadata['width'] ?? null) : null,
        'height' => is_array($metadata) ? ($metadata['height'] ?? null) : null,
    ];
}

function wordvel_manifest(): ?array
{
    $manifestPath = wordvel_laravel_path() . '/storage/wordvel/manifest.json';

    if (! file_exists($manifestPath)) {
        return null;
    }

    $manifest = json_decode((string) file_get_contents($manifestPath), true);

    return is_array($manifest) ? $manifest : null;
}

function wordvel_region_values(): array
{
    $values = get_option('wordvel_region_values');

    return is_array($values) ? $values : [];
}

function wordvel_theme_options_schema(): ?array
{
    $manifest = wordvel_manifest();
    $schema = is_array($manifest) ? ($manifest['theme_options'] ?? null) : null;

    return is_array($schema) ? $schema : null;
}

function wordvel_theme_options_values(): array
{
    $values = get_option('wordvel_theme_options');

    return is_array($values) ? $values : [];
}

function wordvel_page_payload(string $pageKey): ?array
{
    $wpPage = get_page_by_path($pageKey);

    if (! $wpPage instanceof WP_Post) {
        return null;
    }

    return [
        'slug' => $pageKey,
        'title' => get_the_title($wpPage),
        'component' => 'Page',
        'wordpress_page' => [
            'id' => $wpPage->ID,
            'slug' => $wpPage->post_name,
            'status' => $wpPage->post_status,
        ],
        'blocks' => wordvel_parse_page_blocks(parse_blocks($wpPage->post_content)),
    ];
}

function wordvel_block_schemas(): array
{
    $manifest = wordvel_manifest();
    $blocks = is_array($manifest) ? ($manifest['blocks'] ?? []) : [];

    return is_array($blocks) ? array_values(array_filter($blocks, static fn (mixed $block): bool => is_array($block))) : [];
}

function wordvel_block_schema(string $blockKey): ?array
{
    foreach (wordvel_block_schemas() as $block) {
        if (($block['key'] ?? null) === $blockKey) {
            return $block;
        }
    }

    return null;
}

function wordvel_register_editor_block(array $block): void
{
    $key = (string) ($block['key'] ?? '');

    if ($key === '') {
        return;
    }

    $attributes = [];

    foreach (($block['fields'] ?? []) as $field) {
        if (! is_array($field)) {
            continue;
        }

        $fieldKey = (string) ($field['key'] ?? '');

        if ($fieldKey === '') {
            continue;
        }

        $isRepeater = ($field['type'] ?? null) === 'repeater';

        $attributes[$fieldKey] = [
            'type' => $isRepeater ? 'array' : 'string',
            'default' => $isRepeater ? ($field['default'] ?? []) : (isset($field['default']) ? (string) $field['default'] : ''),
        ];
    }

    register_block_type('wordvel/' . $key, [
        'api_version' => 3,
        'title' => (string) ($block['name'] ?? ucfirst($key)),
        'category' => 'wordvel',
        'attributes' => $attributes,
        'editor_script' => 'wordvel-editor-blocks',
        'render_callback' => static fn (): string => '',
    ]);
}

function wordvel_parse_page_blocks(array $blocks): array
{
    $parsed = [];

    foreach ($blocks as $block) {
        if (! is_array($block)) {
            continue;
        }

        $name = (string) ($block['blockName'] ?? '');

        if ($name === '') {
            $html = trim((string) ($block['innerHTML'] ?? ''));

            if ($html !== '') {
                $parsed[] = [
                    'id' => 'html-' . count($parsed),
                    'type' => 'html',
                    'component' => 'Html',
                    'data' => [
                        'html' => $html,
                    ],
                ];
            }

            continue;
        }

        if (! str_starts_with($name, 'wordvel/')) {
            $parsed[] = [
                'id' => sanitize_title($name) . '-' . count($parsed),
                'type' => 'wordpress_block',
                'component' => 'WordPressBlock',
                'data' => [
                    'name' => $name,
                    'attrs' => $block['attrs'] ?? [],
                    'html' => render_block($block),
                ],
            ];

            if (($block['innerBlocks'] ?? []) !== []) {
                $parsed = array_merge($parsed, wordvel_parse_page_blocks($block['innerBlocks']));
            }
            continue;
        }

        $blockKey = substr($name, strlen('wordvel/'));
        $schema = wordvel_block_schema($blockKey);

        $parsed[] = [
            'id' => $blockKey,
            'type' => $blockKey,
            'component' => (string) ($schema['component'] ?? $blockKey),
            'data' => wordvel_block_data_payload($schema ?? ['fields' => []], $block['attrs'] ?? []),
        ];
    }

    return $parsed;
}

function wordvel_region_schema(string $regionKey): ?array
{
    $manifest = wordvel_manifest();
    $regions = wordvel_sorted_regions(is_array($manifest) ? ($manifest['regions'] ?? []) : []);

    foreach ($regions as $region) {
        if (! is_array($region)) {
            continue;
        }

        if (($region['key'] ?? null) === $regionKey) {
            return $region;
        }
    }

    return null;
}

function wordvel_sorted_regions(mixed $regions): array
{
    if (! is_array($regions)) {
        return [];
    }

    $regions = array_filter($regions, static fn (mixed $region): bool => is_array($region));
    usort($regions, static fn (array $a, array $b): int => ($a['order'] ?? 50) <=> ($b['order'] ?? 50));

    return $regions;
}

function wordvel_render_region_form(array $region, array $values): void
{
    $regionKey = (string) ($region['key'] ?? '');

    if ($regionKey === '') {
        return;
    }

    printf('<h2>%s</h2>', esc_html((string) ($region['name'] ?? ucfirst($regionKey))));
    echo '<div style="display: grid; gap: 16px; max-width: 960px;">';

    foreach (($region['blocks'] ?? []) as $block) {
        if (is_array($block)) {
            wordvel_render_block_form($regionKey, $block, $values[$regionKey] ?? []);
        }
    }

    echo '</div>';
}

function wordvel_render_block_form(string $regionKey, array $block, mixed $regionValues): void
{
    $blockKey = (string) ($block['key'] ?? '');

    if ($blockKey === '') {
        return;
    }

    $regionValues = is_array($regionValues) ? $regionValues : [];
    $blockValues = is_array($regionValues[$blockKey] ?? null) ? $regionValues[$blockKey] : [];

    echo '<div style="background: #fff; border: 1px solid #c3c4c7; padding: 16px;">';
    printf('<h3 style="margin-top: 0;">%s</h3>', esc_html((string) ($block['label'] ?? ucfirst($blockKey))));

    if (($block['fields'] ?? []) === []) {
        echo '<p>This block uses WordPress site data and has no editable fields.</p>';
    }

    foreach (($block['fields'] ?? []) as $field) {
        if (is_array($field)) {
            wordvel_render_field_form($blockKey, $field, $blockValues);
        }
    }

    echo '</div>';
}

function wordvel_render_field_form(string $blockKey, array $field, array $blockValues): void
{
    $fieldKey = (string) ($field['key'] ?? '');

    if ($fieldKey === '') {
        return;
    }

    $name = sprintf('region[%s][%s]', $blockKey, $fieldKey);
    $value = (string) ($blockValues[$fieldKey] ?? ($field['default'] ?? ''));
    $type = (string) ($field['type'] ?? 'text');
    $label = (string) ($field['label'] ?? ucfirst($fieldKey));

    printf('<p><label><strong>%s</strong><br>', esc_html($label));

    if ($type === 'menu_location') {
        echo '<select name="' . esc_attr($name) . '">';

        foreach (array_keys(get_registered_nav_menus()) as $location) {
            printf(
                '<option value="%s"%s>%s</option>',
                esc_attr($location),
                selected($value, $location, false),
                esc_html($location),
            );
        }

        echo '</select>';
    } else {
        printf(
            '<input type="%s" name="%s" value="%s" class="regular-text">',
            esc_attr($type === 'url' ? 'url' : 'text'),
            esc_attr($name),
            esc_attr($value),
        );
    }

    echo '</label></p>';
}

function wordvel_sanitize_region_value(array $region, mixed $input): array
{
    $input = is_array($input) ? wp_unslash($input) : [];
    $values = [];

    foreach (($region['blocks'] ?? []) as $block) {
        if (! is_array($block)) {
            continue;
        }

        $blockKey = (string) ($block['key'] ?? '');

        if ($blockKey === '') {
            continue;
        }

        foreach (($block['fields'] ?? []) as $field) {
            if (! is_array($field)) {
                continue;
            }

            $fieldKey = (string) ($field['key'] ?? '');

            if ($fieldKey === '') {
                continue;
            }

            $raw = $input[$blockKey][$fieldKey] ?? ($field['default'] ?? '');
            $values[$blockKey][$fieldKey] = wordvel_sanitize_field_value((string) $raw, (string) ($field['type'] ?? 'text'));
        }
    }

    return $values;
}

function wordvel_sanitize_theme_options_value(array $schema, mixed $input): array
{
    $input = is_array($input) ? wp_unslash($input) : [];
    $values = [];

    foreach (($schema['groups'] ?? []) as $group) {
        if (! is_array($group)) {
            continue;
        }

        $groupKey = (string) ($group['key'] ?? '');

        if ($groupKey === '') {
            continue;
        }

        foreach (($group['fields'] ?? []) as $field) {
            if (! is_array($field)) {
                continue;
            }

            $fieldKey = (string) ($field['key'] ?? '');

            if ($fieldKey === '') {
                continue;
            }

            $raw = $input[$groupKey][$fieldKey] ?? ($field['default'] ?? '');
            $values[$groupKey][$fieldKey] = wordvel_sanitize_field_value((string) $raw, (string) ($field['type'] ?? 'text'));
        }
    }

    return $values;
}

function wordvel_sanitize_field_value(string $value, string $type): string
{
    return match ($type) {
        'url' => esc_url_raw($value),
        'menu_location' => sanitize_key($value),
        'select' => sanitize_key($value),
        default => sanitize_text_field($value),
    };
}

function wordvel_laravel_path(): string
{
    return defined('WORDVEL_LARAVEL_PATH')
        ? WORDVEL_LARAVEL_PATH
        : dirname(__DIR__, 4) . '/laravel';
}
