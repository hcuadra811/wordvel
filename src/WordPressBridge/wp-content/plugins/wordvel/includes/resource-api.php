<?php
/**
 * WordPress-authenticated proxy for the Laravel resource editor API.
 *
 * Editor scripts call this endpoint with the WordPress login cookie and a REST
 * nonce. WordPress checks the user's capability, then forwards the request to
 * Laravel with the bridge key, which Laravel requires on every resource route.
 */

declare(strict_types=1);

const WORDVEL_RESOURCE_API_NAMESPACE = 'wordvel-editor/v1';

add_action('rest_api_init', static function (): void {
    $args = [
        'methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'],
        'callback' => 'wordvel_proxy_resource_request',
        'permission_callback' => 'wordvel_resource_api_permission',
    ];

    register_rest_route(WORDVEL_RESOURCE_API_NAMESPACE, '/resources', $args);
    register_rest_route(WORDVEL_RESOURCE_API_NAMESPACE, '/resources/(?P<path>.+)', $args);
});

/**
 * The browser-facing URL of the resource API, or '' when the manifest has no
 * Laravel resource API to forward to.
 */
function wordvel_resource_api_url(): string
{
    if (wordvel_resource_api_target() === '') {
        return '';
    }

    return untrailingslashit(rest_url(WORDVEL_RESOURCE_API_NAMESPACE . '/resources'));
}

function wordvel_resource_api_target(): string
{
    $manifest = wordvel_manifest();

    return is_array($manifest)
        ? untrailingslashit((string) ($manifest['editor']['resource_api_url'] ?? ''))
        : '';
}

/**
 * Relationship pickers in the block editor only look up options, so anyone who
 * can edit posts may do that. Every other resource request needs the same
 * capability as the resource admin screens.
 */
function wordvel_resource_api_permission(WP_REST_Request $request): bool
{
    $segments = wordvel_resource_api_segments($request);

    if (
        $request->get_method() === 'GET'
        && is_array($segments)
        && count($segments) === 2
        && $segments[1] === 'options'
    ) {
        return current_user_can('edit_posts');
    }

    return current_user_can('manage_options');
}

function wordvel_proxy_resource_request(WP_REST_Request $request): WP_REST_Response
{
    $target = wordvel_resource_api_target();

    if ($target === '') {
        return wordvel_proxy_error('The WordVel manifest has no resource API URL. Run php artisan wordvel:manifest.', 502);
    }

    $key = wordvel_bridge_key();

    if ($key === '') {
        return wordvel_proxy_error('The WordVel bridge key is unavailable. Laravel must boot with APP_KEY or WORDVEL_BRIDGE_KEY set.', 500);
    }

    $segments = wordvel_resource_api_segments($request);

    if ($segments === null) {
        return wordvel_proxy_error('Invalid WordVel resource path.', 400);
    }

    $url = $target . implode('', array_map(static fn (string $segment): string => '/' . rawurlencode($segment), $segments));
    $query = $request->get_query_params();
    unset($query['rest_route']);

    if ($query !== []) {
        $url .= '?' . http_build_query($query);
    }

    $headers = [];

    foreach (['accept', 'accept-language', 'content-type', 'x-wordvel-language'] as $name) {
        $value = $request->get_header($name);

        if (is_string($value) && $value !== '') {
            $headers[$name] = $value;
        }
    }

    $headers[strtolower(\Wordvel\Bridge\BridgeKey::HEADER)] = $key;

    return wordvel_forward_request($request, $url, $headers);
}

/**
 * The path below /resources as segments, or null when a segment is empty or a
 * dot segment, which could otherwise walk the forwarded URL out of the
 * resource API.
 *
 * @return array<int, string>|null
 */
function wordvel_resource_api_segments(WP_REST_Request $request): ?array
{
    $path = trim((string) ($request->get_url_params()['path'] ?? ''), '/');

    if ($path === '') {
        return [];
    }

    $segments = explode('/', $path);

    foreach ($segments as $segment) {
        if ($segment === '' || $segment === '.' || $segment === '..') {
            return null;
        }
    }

    return $segments;
}

function wordvel_bridge_key(): string
{
    if (! class_exists(\Wordvel\Bridge\BridgeKey::class)) {
        return '';
    }

    try {
        return \Wordvel\Bridge\BridgeKey::value();
    } catch (Throwable) {
        return '';
    }
}
