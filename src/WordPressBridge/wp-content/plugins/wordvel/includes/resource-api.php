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
 * The REST path editor scripts pass to wp.apiFetch, or '' when the manifest
 * has no Laravel resource API to forward to. apiFetch adds the site's REST
 * root and the nonce, and refreshes the nonce when it expires.
 */
function wordvel_resource_api_path(): string
{
    if (wordvel_resource_api_target() === '') {
        return '';
    }

    return '/' . WORDVEL_RESOURCE_API_NAMESPACE . '/resources';
}

function wordvel_resource_api_target(): string
{
    $manifest = wordvel_manifest();

    return is_array($manifest)
        ? untrailingslashit((string) ($manifest['editor']['resource_api_url'] ?? ''))
        : '';
}

/**
 * Relationship pickers in the block editor look up options for the resources
 * that block fields reference, so anyone who can edit posts may do that for
 * those resources. Every other resource request needs the same capability as
 * the resource admin screens.
 */
function wordvel_resource_api_permission(WP_REST_Request $request): bool
{
    $segments = wordvel_resource_api_segments($request);

    if (
        $request->get_method() === 'GET'
        && is_array($segments)
        && count($segments) === 2
        && $segments[1] === 'options'
        && in_array($segments[0], wordvel_block_relationship_resources(), true)
    ) {
        return current_user_can('edit_posts');
    }

    return current_user_can('manage_options');
}

/**
 * Resource keys referenced by relationship fields of manifest blocks,
 * including fields nested inside repeaters.
 *
 * @return array<int, string>
 */
function wordvel_block_relationship_resources(): array
{
    $manifest = wordvel_manifest();
    $blocks = is_array($manifest) && is_array($manifest['blocks'] ?? null) ? $manifest['blocks'] : [];
    $resources = [];
    $collect = static function (array $fields) use (&$collect, &$resources): void {
        foreach ($fields as $field) {
            if (! is_array($field)) {
                continue;
            }

            $resource = $field['options']['resource'] ?? null;

            if (($field['type'] ?? null) === 'relationship' && is_string($resource) && $resource !== '') {
                $resources[$resource] = true;
            }

            if (is_array($field['fields'] ?? null)) {
                $collect($field['fields']);
            }
        }
    };

    foreach ($blocks as $block) {
        if (is_array($block) && is_array($block['fields'] ?? null)) {
            $collect($block['fields']);
        }
    }

    return array_keys($resources);
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
    // WordPress routing and apiFetch parameters, not part of the resource API.
    unset($query['rest_route'], $query['_locale'], $query['_method']);

    if ($query !== []) {
        $url .= '?' . http_build_query($query);
    }

    // Always ask Laravel for JSON: an HTML error page, such as Laravel's debug
    // page, would echo the request headers, bridge key included.
    $headers = ['accept' => 'application/json'];

    foreach (['accept-language', 'content-type', 'x-wordvel-language'] as $name) {
        $value = $request->get_header($name);

        if (is_string($value) && $value !== '') {
            $headers[$name] = $value;
        }
    }

    $headers[strtolower(\Wordvel\Bridge\BridgeKey::HEADER)] = $key;

    return wordvel_forward_request($request, $url, $headers, true);
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
