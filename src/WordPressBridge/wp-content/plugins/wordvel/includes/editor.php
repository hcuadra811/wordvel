<?php
/**
 * Gutenberg block registration and preview helpers for WordVel.
 */

declare(strict_types=1);

add_action('init', static function (): void {
    wp_register_script(
        'wordvel-editor-blocks',
        plugins_url('../assets/editor-blocks.js', __FILE__),
        ['wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-data', 'wp-plugins'],
        (string) filemtime(dirname(__DIR__) . '/assets/editor-blocks.js'),
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
    wp_add_inline_script(
        'wordvel-editor-blocks',
        'window.wordvelBlocks = ' . wp_json_encode(wordvel_block_schemas()) . ';' . "\n" .
        'window.wordvelEditorPreviews = ' . wp_json_encode(wordvel_editor_preview_templates()) . ';' . "\n" .
        'window.wordvelResourceApiUrl = ' . wp_json_encode(untrailingslashit((string) (wordvel_manifest()['editor']['resource_api_url'] ?? ''))) . ';' . "\n" .
        'window.wordvelResourceSchemas = ' . wp_json_encode(wordvel_resource_schemas()) . ';',
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

    if ($css === '' && is_file(dirname(__DIR__) . '/assets/editor-style.css')) {
        $css = (string) file_get_contents(dirname(__DIR__) . '/assets/editor-style.css');
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

.wordvel-relationship-control,
.wordvel-relationship-selected,
.wordvel-relationship-modal-fields {
    display: grid;
    gap: 8px;
}

.wordvel-relationship-label {
    display: block;
    color: #1e1e1e;
    font-size: 11px;
    font-weight: 500;
    line-height: 1.4;
    text-transform: uppercase;
}

.wordvel-relationship-combobox {
    position: relative;
}

.wordvel-relationship-combobox input {
    width: 100%;
}

.wordvel-relationship-menu {
    position: absolute;
    z-index: 40;
    right: 0;
    left: 0;
    max-height: 240px;
    overflow: auto;
    border: 1px solid #8c8f94;
    background: #fff;
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.14);
}

.wordvel-relationship-menu button:not(.components-button) {
    display: block;
    width: 100%;
    padding: 8px 10px;
    border: 0;
    border-bottom: 1px solid #f0f0f1;
    background: #fff;
    text-align: left;
}

.wordvel-relationship-menu button:hover,
.wordvel-relationship-menu button.is-selected {
    background: #f6f7f7;
}

.wordvel-relationship-menu p {
    margin: 0;
    padding: 10px;
    color: #646970;
}

.wordvel-relationship-row {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 6px 0;
}

.wordvel-relationship-row > span {
    flex: 1;
    min-width: 0;
}

.wordvel-relationship-row.is-sortable {
    cursor: grab;
}

.wordvel-relationship-drag {
    flex: 0 0 auto !important;
    color: #646970;
    cursor: grab;
}

.wordvel-relationship-modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    margin-top: 16px;
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
        $isRelationshipList = ($field['type'] ?? null) === 'relationship'
            && (bool) ($field['options']['multiple'] ?? false);
        $isObject = in_array(($field['type'] ?? null), ['image', 'media', 'localized_text'], true);

        $attributes[$fieldKey] = [
            'type' => ($isRepeater || $isRelationshipList) ? 'array' : ($isObject ? 'object' : 'string'),
            'default' => ($isRepeater || $isRelationshipList) ? ($field['default'] ?? []) : ($isObject ? ($field['default'] ?? []) : (isset($field['default']) ? (string) $field['default'] : '')),
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
