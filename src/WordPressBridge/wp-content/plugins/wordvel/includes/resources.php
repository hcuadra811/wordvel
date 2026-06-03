<?php

declare(strict_types=1);

add_action('admin_menu', static function (): void {
    $manifest = wordvel_manifest();
    $resources = is_array($manifest) && is_array($manifest['resources'] ?? null)
        ? $manifest['resources']
        : [];

    $groups = wordvel_resource_menu_groups($resources);

    foreach ($groups as $groupKey => $group) {
        add_menu_page(
            (string) ($group['label'] ?? 'WordVel Resources'),
            (string) ($group['label'] ?? 'WordVel Resources'),
            'manage_options',
            'wordvel-resource-group-' . $groupKey,
            static fn () => wordvel_resource_group_page((string) $groupKey),
            (string) ($group['icon'] ?? 'dashicons-database'),
            isset($group['position']) ? (int) $group['position'] : 59,
        );

        foreach ($group['resources'] as $resource) {
            if (! is_array($resource)) {
                continue;
            }

            $options = is_array($resource['options'] ?? null) ? $resource['options'] : [];

            if (($options['show_in_menu'] ?? true) === false) {
                continue;
            }

            $key = (string) ($resource['key'] ?? '');

            if ($key === '') {
                continue;
            }

            add_submenu_page(
                'wordvel-resource-group-' . $groupKey,
                (string) ($resource['plural_label'] ?? ucfirst($key)),
                (string) ($resource['plural_label'] ?? ucfirst($key)),
                'manage_options',
                'wordvel-resource-' . $key,
                static fn () => wordvel_resource_editor_page($key),
            );
        }
    }

    foreach ($resources as $resource) {
        if (! is_array($resource)) {
            continue;
        }

        if (wordvel_resource_group_key($resource) !== 'wordvel') {
            continue;
        }

        $options = is_array($resource['options'] ?? null) ? $resource['options'] : [];

        if (($options['show_in_menu'] ?? true) === false) {
            continue;
        }

        $key = (string) ($resource['key'] ?? '');

        if ($key === '') {
            continue;
        }

        add_submenu_page(
            'wordvel-regions',
            (string) ($resource['plural_label'] ?? ucfirst($key)),
            (string) ($resource['plural_label'] ?? ucfirst($key)),
            'manage_options',
            'wordvel-resource-' . $key,
            static fn () => wordvel_resource_editor_page($key),
        );
    }
}, 20);

function wordvel_resource_menu_groups(array $resources): array
{
    $groups = [];

    foreach ($resources as $resource) {
        if (! is_array($resource)) {
            continue;
        }

        $groupKey = wordvel_resource_group_key($resource);

        if ($groupKey === 'wordvel') {
            continue;
        }

        $options = is_array($resource['options'] ?? null) ? $resource['options'] : [];
        $groups[$groupKey] ??= [
            'label' => $options['menu_label'] ?? str_replace('-', ' ', ucfirst($groupKey)),
            'icon' => $options['menu_icon'] ?? 'dashicons-database',
            'position' => $options['menu_position'] ?? 59,
            'resources' => [],
        ];
        $groups[$groupKey]['resources'][] = $resource;
    }

    return $groups;
}

function wordvel_resource_group_key(array $resource): string
{
    $options = is_array($resource['options'] ?? null) ? $resource['options'] : [];
    $label = is_string($options['menu_label'] ?? null) ? $options['menu_label'] : null;

    return $label !== null && $label !== ''
        ? sanitize_title($label)
        : 'wordvel';
}

function wordvel_resource_group_page(string $groupKey): void
{
    $groupResources = wordvel_resource_group_resources($groupKey);

    echo '<div class="wrap">';
    $firstResource = $groupResources[0] ?? [];
    printf('<h1>%s</h1>', esc_html($firstResource['options']['menu_label'] ?? 'Resources'));
    echo '<div id="wordvel-resource-overview-root" class="wordvel-resource-overview-root"></div>';
    echo '</div>';
}

function wordvel_resource_group_resources(string $groupKey): array
{
    $manifest = wordvel_manifest();
    $resources = is_array($manifest) && is_array($manifest['resources'] ?? null)
        ? $manifest['resources']
        : [];
    $groupResources = array_values(array_filter($resources, static fn (mixed $resource): bool => is_array($resource) && wordvel_resource_group_key($resource) === $groupKey));

    usort($groupResources, static function (array $left, array $right): int {
        $leftOptions = is_array($left['options'] ?? null) ? $left['options'] : [];
        $rightOptions = is_array($right['options'] ?? null) ? $right['options'] : [];

        return ((int) ($leftOptions['overview_order'] ?? 100)) <=> ((int) ($rightOptions['overview_order'] ?? 100));
    });

    return $groupResources;
}

add_action('admin_enqueue_scripts', static function (string $hook): void {
    if (! str_contains($hook, 'wordvel-resource-')) {
        return;
    }

    $page = isset($_GET['page']) ? sanitize_key((string) $_GET['page']) : '';
    $resourceKey = str_replace('wordvel-resource-', '', $page);
    $schema = wordvel_resource_schema($resourceKey);
    $manifest = wordvel_manifest();
    $apiBaseUrl = is_array($manifest) ? (string) ($manifest['editor']['resource_api_url'] ?? '') : '';

    if (! str_starts_with($page, 'wordvel-resource-group-') && ($schema === null || $apiBaseUrl === '')) {
        return;
    }

    wp_enqueue_style(
        'wordvel-resource-editor',
        plugins_url('../assets/admin.css', __FILE__),
        [],
        (string) filemtime(dirname(__DIR__) . '/assets/admin.css'),
    );

    if ($schema === null || $apiBaseUrl === '') {
        if (str_starts_with($page, 'wordvel-resource-group-') && $apiBaseUrl !== '') {
            $groupKey = str_replace('wordvel-resource-group-', '', $page);
            $resources = array_values(array_filter(
                wordvel_resource_group_resources($groupKey),
                static function (array $resource): bool {
                    $options = is_array($resource['options'] ?? null) ? $resource['options'] : [];

                    return ($options['show_in_overview'] ?? true) !== false;
                },
            ));

            wp_enqueue_script(
                'wordvel-resource-overview',
                plugins_url('../assets/resource-overview.js', __FILE__),
                [],
                (string) filemtime(dirname(__DIR__) . '/assets/resource-overview.js'),
                true,
            );

            wp_add_inline_script(
                'wordvel-resource-overview',
                'window.wordvelResourceGroup = ' . wp_json_encode([
                    'resources' => $resources,
                    'apiBaseUrl' => untrailingslashit($apiBaseUrl),
                    'adminUrl' => admin_url('admin.php'),
                ]) . ';',
                'before',
            );
        }

        return;
    }

    wp_enqueue_media();
    wp_enqueue_editor();

    wp_enqueue_script(
        'wordvel-resource-editor',
        plugins_url('../assets/resource-editor.js', __FILE__),
        ['wp-components', 'wp-element'],
        (string) filemtime(dirname(__DIR__) . '/assets/resource-editor.js'),
        true,
    );

    wp_add_inline_script(
        'wordvel-resource-editor',
        'window.wordvelResourceEditor = ' . wp_json_encode([
            'resourceKey' => $resourceKey,
            'schema' => $schema,
            'resources' => wordvel_resource_schemas(),
            'apiBaseUrl' => untrailingslashit($apiBaseUrl),
            'adminUrl' => admin_url('admin.php'),
            'adminPage' => 'wordvel-resource-' . $resourceKey,
            'initialId' => isset($_GET['record']) ? sanitize_text_field((string) $_GET['record']) : null,
            'initialLanguage' => isset($_GET['lang']) ? sanitize_text_field((string) $_GET['lang']) : null,
            'defaultLanguage' => 'en',
            'languages' => [
                ['code' => 'en', 'label' => 'English'],
                ['code' => 'es', 'label' => 'Spanish'],
            ],
        ]) . ';',
        'before',
    );
});

function wordvel_resource_editor_page(string $resourceKey): void
{
    if (! current_user_can('manage_options')) {
        wp_die('You do not have permission to manage WordVel resources.');
    }

    $schema = wordvel_resource_schema($resourceKey);

    echo '<div class="wrap wordvel-resource-wrap">';

    if ($schema === null) {
        echo '<h1>Resource Not Found</h1>';
        echo '<p>This resource is not defined in Laravel.</p>';
        echo '</div>';

        return;
    }

    printf(
        '<h1>%s</h1>',
        esc_html((string) ($schema['plural_label'] ?? ucfirst($resourceKey))),
    );
    echo '<p class="description">Fields are inferred from Laravel Data DTOs and saved through the WordVel resource API.</p>';
    echo '<div id="wordvel-resource-editor-root" class="wordvel-resource-editor-root"></div>';
    echo '</div>';
}

function wordvel_resource_schemas(): array
{
    $manifest = wordvel_manifest();
    return is_array($manifest) && is_array($manifest['resources'] ?? null)
        ? $manifest['resources']
        : [];
}

function wordvel_resource_schema(string $resourceKey): ?array
{
    foreach (wordvel_resource_schemas() as $resource) {
        if (is_array($resource) && ($resource['key'] ?? null) === $resourceKey) {
            return $resource;
        }
    }

    return null;
}
