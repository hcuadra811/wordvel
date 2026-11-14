<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use Wordvel\Bridge\BridgeKey;
use WP_REST_Request;

final class WordPressResourceProxyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        require_once dirname(__DIR__) . '/Support/wordpress-stubs.php';

        $GLOBALS['wordvel_wp_stub']['capabilities'] = [];
        $GLOBALS['wordvel_wp_stub']['remote_requests'] = [];
        $GLOBALS['wordvel_wp_stub']['remote_response'] = null;
        $GLOBALS['wordvel_wp_stub']['manifest'] = [
            'editor' => ['resource_api_url' => 'http://wordvel-api.test/api/wordvel/resources'],
        ];
    }

    public function test_it_forwards_to_laravel_with_the_bridge_key(): void
    {
        $request = new WP_REST_Request(
            'PUT',
            [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'X-Wordvel-Language' => 'es',
                'X-WP-Nonce' => 'nonce',
                'Cookie' => 'wordpress_logged_in=abc',
                'Authorization' => 'Basic abc',
            ],
            ['lang' => 'es', 'ids' => ['1', '2'], 'rest_route' => '/wordvel-editor/v1/resources/orders/7'],
            ['path' => 'orders/7'],
            '{"values":{"status":"paid"}}',
        );

        $response = wordvel_proxy_resource_request($request);

        $this->assertSame(200, $response->get_status());
        $this->assertCount(1, $GLOBALS['wordvel_wp_stub']['remote_requests']);

        ['url' => $url, 'args' => $args] = $GLOBALS['wordvel_wp_stub']['remote_requests'][0];

        $this->assertSame('http://wordvel-api.test/api/wordvel/resources/orders/7?' . http_build_query(['lang' => 'es', 'ids' => ['1', '2']]), $url);
        $this->assertSame('PUT', $args['method']);
        $this->assertSame('{"values":{"status":"paid"}}', $args['body']);
        $this->assertSame(BridgeKey::value(), $args['headers']['x-wordvel-bridge-key']);
        $this->assertSame('es', $args['headers']['x-wordvel-language']);
        $this->assertSame('application/json', $args['headers']['content-type']);
        $this->assertArrayNotHasKey('cookie', $args['headers']);
        $this->assertArrayNotHasKey('authorization', $args['headers']);
        $this->assertArrayNotHasKey('x-wp-nonce', $args['headers']);
    }

    public function test_laravel_accepts_the_key_the_proxy_sends(): void
    {
        wordvel_proxy_resource_request(new WP_REST_Request('GET'));

        $headers = $GLOBALS['wordvel_wp_stub']['remote_requests'][0]['args']['headers'];

        $this->assertSame('http://wordvel-api.test/api/wordvel/resources', $GLOBALS['wordvel_wp_stub']['remote_requests'][0]['url']);
        $this->getJson('/api/wordvel/resources', $headers)->assertOk();
    }

    public function test_it_encodes_path_segments(): void
    {
        wordvel_proxy_resource_request(new WP_REST_Request('GET', [], [], ['path' => 'products/a b?c']));

        $this->assertSame(
            'http://wordvel-api.test/api/wordvel/resources/products/a%20b%3Fc',
            $GLOBALS['wordvel_wp_stub']['remote_requests'][0]['url'],
        );
    }

    public function test_it_refuses_dot_segments(): void
    {
        foreach (['../../internal', 'orders/..', 'orders/./1', 'orders//1'] as $path) {
            $response = wordvel_proxy_resource_request(new WP_REST_Request('GET', [], [], ['path' => $path]));

            $this->assertSame(400, $response->get_status(), $path);
        }

        $this->assertSame([], $GLOBALS['wordvel_wp_stub']['remote_requests']);
    }

    public function test_it_refuses_to_forward_without_a_manifest_target(): void
    {
        $GLOBALS['wordvel_wp_stub']['manifest'] = null;

        $this->assertSame('', wordvel_resource_api_url());
        $this->assertSame(502, wordvel_proxy_resource_request(new WP_REST_Request('GET'))->get_status());
        $this->assertSame([], $GLOBALS['wordvel_wp_stub']['remote_requests']);
    }

    public function test_it_refuses_to_forward_without_a_bridge_key(): void
    {
        config(['wordvel.bridge.key' => null, 'app.key' => null]);

        $this->assertSame(500, wordvel_proxy_resource_request(new WP_REST_Request('GET'))->get_status());
        $this->assertSame([], $GLOBALS['wordvel_wp_stub']['remote_requests']);
    }

    public function test_browsers_get_the_wordpress_proxy_url(): void
    {
        $this->assertSame('http://wordvel-wp.test/wp-json/wordvel-editor/v1/resources', wordvel_resource_api_url());
    }

    public function test_option_lookups_need_edit_posts_and_everything_else_needs_manage_options(): void
    {
        $options = new WP_REST_Request('GET', [], [], ['path' => 'products/options']);
        $index = new WP_REST_Request('GET', [], [], ['path' => 'orders']);
        $schemas = new WP_REST_Request('GET');
        $create = new WP_REST_Request('POST', [], [], ['path' => 'products']);
        $postOptions = new WP_REST_Request('POST', [], [], ['path' => 'products/options']);

        $this->assertFalse(wordvel_resource_api_permission($options));
        $this->assertFalse(wordvel_resource_api_permission($index));

        $GLOBALS['wordvel_wp_stub']['capabilities'] = ['edit_posts'];

        $this->assertTrue(wordvel_resource_api_permission($options));
        $this->assertFalse(wordvel_resource_api_permission($index));
        $this->assertFalse(wordvel_resource_api_permission($schemas));
        $this->assertFalse(wordvel_resource_api_permission($create));
        $this->assertFalse(wordvel_resource_api_permission($postOptions));

        $GLOBALS['wordvel_wp_stub']['capabilities'] = ['edit_posts', 'manage_options'];

        $this->assertTrue(wordvel_resource_api_permission($index));
        $this->assertTrue(wordvel_resource_api_permission($schemas));
        $this->assertTrue(wordvel_resource_api_permission($create));
    }
}
