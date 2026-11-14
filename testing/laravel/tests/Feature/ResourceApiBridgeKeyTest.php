<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;
use Wordvel\Bridge\BridgeKey;
use Wordvel\Http\Middleware\VerifyBridgeKey;

final class ResourceApiBridgeKeyTest extends TestCase
{
    public function test_resource_routes_refuse_requests_without_the_bridge_key(): void
    {
        $this->getJson('/api/wordvel/resources')
            ->assertStatus(401)
            ->assertJsonPath('status', false);

        $this->getJson('/api/wordvel/resources/orders')->assertStatus(401);
        $this->postJson('/api/wordvel/resources/categories', ['values' => ['name' => 'probe']])->assertStatus(401);
        $this->putJson('/api/wordvel/resources/orders/1', ['values' => ['status' => 'paid']])->assertStatus(401);
        $this->deleteJson('/api/wordvel/resources/orders/1')->assertStatus(401);
        $this->postJson('/api/wordvel/resources/products/1/relationships/tracks')->assertStatus(401);
        $this->postJson('/api/wordvel/resources/products/1/relationships/tracks/media')->assertStatus(401);
    }

    public function test_resource_routes_refuse_a_wrong_bridge_key(): void
    {
        $this->getJson('/api/wordvel/resources', [BridgeKey::HEADER => 'not-the-key'])->assertStatus(401);
    }

    public function test_resource_routes_accept_the_bridge_key(): void
    {
        $this->getJson('/api/wordvel/resources', [BridgeKey::HEADER => BridgeKey::value()])
            ->assertOk()
            ->assertJsonPath('status', true);
    }

    public function test_the_key_is_derived_from_the_app_key_when_not_configured(): void
    {
        config(['wordvel.bridge.key' => null]);

        $this->assertSame(
            hash_hmac('sha256', 'wordvel-bridge', (string) config('app.key')),
            BridgeKey::value(),
        );
        $this->assertNotSame((string) config('app.key'), BridgeKey::value());
    }

    public function test_a_configured_key_replaces_the_derived_one(): void
    {
        $derived = BridgeKey::value();
        config(['wordvel.bridge.key' => 'configured-secret']);

        $this->getJson('/api/wordvel/resources', [BridgeKey::HEADER => 'configured-secret'])->assertOk();
        $this->getJson('/api/wordvel/resources', [BridgeKey::HEADER => $derived])->assertStatus(401);
    }

    public function test_every_request_is_refused_when_no_key_can_be_resolved(): void
    {
        config(['wordvel.bridge.key' => null, 'app.key' => null]);

        $this->assertSame('', BridgeKey::value());
        $this->assertFalse(BridgeKey::matches(''));
        $this->getJson('/api/wordvel/resources', [BridgeKey::HEADER => ''])->assertStatus(401);
        $this->getJson('/api/wordvel/resources', [BridgeKey::HEADER => hash_hmac('sha256', 'wordvel-bridge', '')])->assertStatus(401);
    }

    public function test_every_resource_route_carries_the_middleware(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(static fn ($route): bool => str_starts_with((string) $route->getName(), 'wordvel.resources.'));

        $this->assertNotEmpty($routes);

        foreach ($routes as $route) {
            $this->assertContains(VerifyBridgeKey::class, $route->gatherMiddleware(), (string) $route->getName());
        }
    }

    public function test_apps_can_attach_the_middleware_by_alias(): void
    {
        $this->assertSame(VerifyBridgeKey::class, app('router')->getMiddleware()['wordvel.bridge'] ?? null);
    }
}
