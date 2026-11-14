<?php

/**
 * Just enough of WordPress to exercise the WordVel bridge plugin's REST proxy
 * functions inside PHPUnit. State lives in $GLOBALS['wordvel_wp_stub'].
 */

declare(strict_types=1);

$GLOBALS['wordvel_wp_stub'] = [
    'capabilities' => [],
    'manifest' => null,
    'remote_requests' => [],
    'remote_response' => null,
];

if (! class_exists('WP_REST_Request')) {
    class WP_REST_Request
    {
        /**
         * @param array<string, string> $headers
         * @param array<string, mixed> $query
         * @param array<string, mixed> $urlParams
         */
        public function __construct(
            private string $method = 'GET',
            private array $headers = [],
            private array $query = [],
            private array $urlParams = [],
            private string $body = '',
        ) {
            $this->headers = array_change_key_case($headers, CASE_LOWER);
        }

        public function get_method(): string
        {
            return $this->method;
        }

        public function get_header(string $name): ?string
        {
            return $this->headers[strtolower($name)] ?? null;
        }

        public function get_query_params(): array
        {
            return $this->query;
        }

        public function get_url_params(): array
        {
            return $this->urlParams;
        }

        public function get_body(): string
        {
            return $this->body;
        }

        public function get_json_params(): ?array
        {
            $decoded = json_decode($this->body, true);

            return is_array($decoded) ? $decoded : null;
        }
    }
}

if (! class_exists('WP_REST_Response')) {
    class WP_REST_Response
    {
        public function __construct(public mixed $data = null, public int $status = 200) {}

        public function get_data(): mixed
        {
            return $this->data;
        }

        public function get_status(): int
        {
            return $this->status;
        }
    }
}

if (! function_exists('add_action')) {
    function add_action(string $hook, callable $callback, int $priority = 10, int $args = 1): void {}
}

if (! function_exists('current_user_can')) {
    function current_user_can(string $capability): bool
    {
        return in_array($capability, $GLOBALS['wordvel_wp_stub']['capabilities'], true);
    }
}

if (! function_exists('untrailingslashit')) {
    function untrailingslashit(string $value): string
    {
        return rtrim($value, '/\\');
    }
}

if (! function_exists('rest_url')) {
    function rest_url(string $path = ''): string
    {
        return 'http://wordvel-wp.test/wp-json/' . ltrim($path, '/');
    }
}

if (! function_exists('wp_json_encode')) {
    function wp_json_encode(mixed $value): string|false
    {
        return json_encode($value);
    }
}

if (! function_exists('wordvel_manifest')) {
    function wordvel_manifest(): ?array
    {
        return $GLOBALS['wordvel_wp_stub']['manifest'];
    }
}

if (! function_exists('wp_remote_request')) {
    function wp_remote_request(string $url, array $args): array
    {
        $GLOBALS['wordvel_wp_stub']['remote_requests'][] = ['url' => $url, 'args' => $args];

        return $GLOBALS['wordvel_wp_stub']['remote_response'] ?? ['code' => 200, 'body' => '{"status":true,"data":[]}'];
    }
}

if (! function_exists('is_wp_error')) {
    function is_wp_error(mixed $value): bool
    {
        return false;
    }
}

if (! function_exists('wp_remote_retrieve_body')) {
    function wp_remote_retrieve_body(array $response): string
    {
        return (string) $response['body'];
    }
}

if (! function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code(array $response): int
    {
        return (int) $response['code'];
    }
}

$plugin = dirname(__DIR__, 4) . '/src/WordPressBridge/wp-content/plugins/wordvel/includes';

require_once $plugin . '/site.php';
require_once $plugin . '/resource-api.php';
