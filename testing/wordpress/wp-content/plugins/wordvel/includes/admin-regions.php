<?php
/**
 * Classic WordVel region and theme option admin screens.
 */

declare(strict_types=1);

add_action('admin_menu', static function (): void {
    add_menu_page(
        'WordVel',
        'WordVel',
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
