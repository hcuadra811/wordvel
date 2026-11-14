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

        $this->assertSame('', wordvel_resource_api_path());
        $this->assertSame(502, wordvel_proxy_resource_request(new WP_REST_Request('GET'))->get_status());
        $this->assertSame([], $GLOBALS['wordvel_wp_stub']['remote_requests']);
    }

    public function test_it_refuses_to_forward_without_a_bridge_key(): void
    {
        config(['wordvel.bridge.key' => null, 'app.key' => null]);

        $this->assertSame(500, wordvel_proxy_resource_request(new WP_REST_Request('GET'))->get_status());
        $this->assertSame([], $GLOBALS['wordvel_wp_stub']['remote_requests']);
    }

    public function test_scripts_get_the_proxy_path_for_api_fetch(): void
    {
        $this->assertSame('/wordvel-editor/v1/resources', wordvel_resource_api_path());

        $GLOBALS['wordvel_wp_stub']['manifest'] = null;

        $this->assertSame('', wordvel_resource_api_path());
    }

    public function test_it_always_asks_laravel_for_json(): void
    {
        wordvel_proxy_resource_request(new WP_REST_Request('GET', ['Accept' => 'text/html'], [], ['path' => 'orders']));

        $this->assertSame('application/json', $GLOBALS['wordvel_wp_stub']['remote_requests'][0]['args']['headers']['accept']);
    }

    public function test_it_never_relays_a_body_that_is_not_json(): void
    {
        $GLOBALS['wordvel_wp_stub']['remote_response'] = [
            'code' => 500,
            'body' => '<html>Whoops! x-wordvel-bridge-key: ' . BridgeKey::value() . '</html>',
        ];

        $response = wordvel_proxy_resource_request(new WP_REST_Request('GET', [], ['ids' => 'abc'], ['path' => 'orders/options']));

        $this->assertSame(500, $response->get_status());
        $this->assertStringNotContainsString(BridgeKey::value(), (string) json_encode($response->get_data()));
        $this->assertSame(false, $response->get_data()['status']);

        $GLOBALS['wordvel_wp_stub']['remote_response'] = ['code' => 200, 'body' => 'plain text'];

        $this->assertSame(502, wordvel_proxy_resource_request(new WP_REST_Request('GET'))->get_status());
    }

    public function test_it_drops_wordpress_and_api_fetch_query_params(): void
    {
        wordvel_proxy_resource_request(new WP_REST_Request(
            'POST',
            [],
            ['_locale' => 'user', '_method' => 'PUT', 'rest_route' => '/wordvel-editor/v1/resources/orders', 'lang' => 'en'],
            ['path' => 'orders'],
        ));

        $this->assertSame('http://wordvel-api.test/api/wordvel/resources/orders?lang=en', $GLOBALS['wordvel_wp_stub']['remote_requests'][0]['url']);
    }

    public function test_option_lookups_for_block_relationships_need_edit_posts_and_everything_else_needs_manage_options(): void
    {
        $GLOBALS['wordvel_wp_stub']['manifest']['blocks'] = [
            [
                'key' => 'featured',
                'fields' => [
                    ['key' => 'title', 'type' => 'text', 'options' => []],
                    [
                        'key' => 'items',
                        'type' => 'repeater',
                        'options' => [],
                        'fields' => [
                            ['key' => 'product', 'type' => 'relationship', 'options' => ['resource' => 'products']],
                        ],
                    ],
                ],
            ],
        ];

        $options = new WP_REST_Request('GET', [], [], ['path' => 'products/options']);
        $unreferencedOptions = new WP_REST_Request('GET', [], [], ['path' => 'orders/options']);
        $index = new WP_REST_Request('GET', [], [], ['path' => 'orders']);
        $schemas = new WP_REST_Request('GET');
        $create = new WP_REST_Request('POST', [], [], ['path' => 'products']);
        $postOptions = new WP_REST_Request('POST', [], [], ['path' => 'products/options']);

        $this->assertSame(['products'], wordvel_block_relationship_resources());
        $this->assertFalse(wordvel_resource_api_permission($options));
        $this->assertFalse(wordvel_resource_api_permission($index));

        $GLOBALS['wordvel_wp_stub']['capabilities'] = ['edit_posts'];

        $this->assertTrue(wordvel_resource_api_permission($options));
        $this->assertFalse(wordvel_resource_api_permission($unreferencedOptions));
        $this->assertFalse(wordvel_resource_api_permission($index));
        $this->assertFalse(wordvel_resource_api_permission($schemas));
        $this->assertFalse(wordvel_resource_api_permission($create));
        $this->assertFalse(wordvel_resource_api_permission($postOptions));

        $GLOBALS['wordvel_wp_stub']['capabilities'] = ['edit_posts', 'manage_options'];

        $this->assertTrue(wordvel_resource_api_permission($unreferencedOptions));
        $this->assertTrue(wordvel_resource_api_permission($index));
        $this->assertTrue(wordvel_resource_api_permission($schemas));
        $this->assertTrue(wordvel_resource_api_permission($create));
    }
}
