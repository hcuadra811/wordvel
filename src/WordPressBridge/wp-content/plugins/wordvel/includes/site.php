<?php
/**
 * REST endpoints and public site payload helpers for WordVel.
 */

declare(strict_types=1);

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
    $manifest = wordvel_manifest();

    register_rest_route('wordvel/v1', '/docs', [
        'methods' => 'GET',
        'callback' => static fn (): WP_REST_Response => new WP_REST_Response(wordvel_docs_payload()),
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
            'callback' => static fn (WP_REST_Request $request): WP_REST_Response => wordvel_proxy_api_route($request, $route),
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

function wordvel_proxy_api_route(WP_REST_Request $request, array $route): WP_REST_Response
{
    $targetUrl = wordvel_proxy_target_url($request, $route);

    if ($targetUrl === '') {
        return wordvel_proxy_error('WordVel route target URL is missing.', 502);
    }

    return wordvel_forward_request($request, $targetUrl, wordvel_proxy_headers($request));
}

/**
 * Sends a WordPress REST request on to Laravel over HTTP and relays the response.
 */
function wordvel_forward_request(WP_REST_Request $request, string $targetUrl, array $headers): WP_REST_Response
{
    $args = [
        'method' => $request->get_method(),
        'headers' => $headers,
        'timeout' => 30,
        'redirection' => 0,
    ];

    if (! in_array($request->get_method(), ['GET', 'HEAD'], true)) {
        $body = $request->get_body();

        if ($body === '') {
            $json = $request->get_json_params();
            $body = is_array($json) ? wp_json_encode($json) : '';
        }

        if ($body !== '') {
            $args['body'] = $body;
            $args['headers']['content-type'] ??= 'application/json';
        }
    }

    $response = wp_remote_request($targetUrl, $args);

    if (is_wp_error($response)) {
        return wordvel_proxy_error($response->get_error_message(), 502);
    }

    $body = wp_remote_retrieve_body($response);
    $decoded = json_decode((string) $body, true);

    return new WP_REST_Response(
        is_array($decoded) ? $decoded : $body,
        (int) wp_remote_retrieve_response_code($response),
    );
}

function wordvel_proxy_error(string $message, int $status): WP_REST_Response
{
    return new WP_REST_Response([
        'status' => false,
        'error' => [
            'message' => $message,
        ],
    ], $status);
}

function wordvel_proxy_target_url(WP_REST_Request $request, array $route): string
{
    $url = (string) ($route['target_url'] ?? '');

    if ($url === '') {
        return '';
    }

    $routeParams = $request->get_url_params();

    foreach ($routeParams as $key => $value) {
        $url = str_replace('{' . $key . '}', rawurlencode((string) $value), $url);
        $url = str_replace('{' . $key . '?}', rawurlencode((string) $value), $url);
    }

    $query = $request->get_query_params();

    foreach (array_keys($routeParams) as $key) {
        unset($query[$key]);
    }

    if ($query !== []) {
        $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
    }

    return $url;
}

function wordvel_proxy_headers(WP_REST_Request $request): array
{
    $headers = [];

    foreach (['authorization', 'accept', 'accept-language', 'content-type'] as $name) {
        $value = $request->get_header($name);

        if (is_string($value) && $value !== '') {
            $headers[$name] = $value;
        }
    }

    return $headers;
}

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

function wordvel_site_icon_payload(): ?array
{
    $iconId = (int) get_option('site_icon');

    if ($iconId <= 0) {
        return null;
    }

    $image = wp_get_attachment_image_src($iconId, 'full');

    if ($image === false) {
        return null;
    }

    return [
        'id' => $iconId,
        'url' => $image[0],
        'width' => $image[1],
        'height' => $image[2],
        'alt' => get_post_meta($iconId, '_wp_attachment_image_alt', true) ?: null,
    ];
}

function wordvel_codex_site_identity(): array
{
    return [
        'logo' => wordvel_logo_payload(),
        'favicon' => wordvel_site_icon_payload(),
    ];
}

function wordvel_codex_set_option(string $key, mixed $value): bool
{
    if ($key === '') {
        return false;
    }

    return update_option($key, $value);
}

function wordvel_codex_set_theme_mod(string $key, mixed $value): bool
{
    if ($key === '') {
        return false;
    }

    set_theme_mod($key, $value);

    return true;
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
