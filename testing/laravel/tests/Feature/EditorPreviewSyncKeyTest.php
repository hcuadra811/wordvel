<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;
use Wordvel\Bridge\BridgeKey;
use Wordvel\Editor\EditorPreviewRepository;

final class EditorPreviewSyncKeyTest extends TestCase
{
    /** @var array<string, string|null> */
    private array $originals = [];

    protected function setUp(): void
    {
        parent::setUp();

        config(['wordvel.bridge.key' => 'sync-secret']);

        // The sync writes the app's real preview artifact, so put it back afterwards.
        foreach ([app(EditorPreviewRepository::class)->previewPath(), app(EditorPreviewRepository::class)->cssPath()] as $path) {
            $this->originals[$path] = File::exists($path) ? File::get($path) : null;
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->originals as $path => $contents) {
            $contents === null ? File::delete($path) : File::put($path, $contents);
        }

        parent::tearDown();
    }

    public function test_the_preview_sync_is_refused_without_the_key(): void
    {
        $payload = ['blocks' => ['hero' => ['html' => '<script>alert(1)</script>']], 'css' => 'body{}'];

        $this->postJson('/api/wordvel/editor-preview', $payload)->assertStatus(401);
        $this->postJson('/api/wordvel/editor-preview', $payload, [BridgeKey::HEADER => 'wrong'])->assertStatus(401);
    }

    public function test_the_preview_sync_is_accepted_with_the_key(): void
    {
        $this->postJson('/api/wordvel/editor-preview', [
            'blocks' => ['hero' => ['html' => '<section>Hero</section>']],
            'css' => '.hero{}',
        ], [BridgeKey::HEADER => 'sync-secret'])->assertOk();
    }

    public function test_reading_the_preview_stays_open(): void
    {
        $this->getJson('/api/wordvel/editor-preview')->assertOk();
        $this->get('/api/wordvel/editor.css')->assertOk();
    }
}
