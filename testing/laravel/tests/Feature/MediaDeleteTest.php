<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use Wordvel\WordPress\MediaRepository;

final class MediaDeleteTest extends TestCase
{
    private string $log;

    protected function setUp(): void
    {
        parent::setUp();

        config(['wordvel.wordpress.path' => 'tests/Fixtures/codex-wordpress']);

        // Written by the stub wp_delete_attachment in the Codex subprocess.
        $this->log = storage_path('framework/testing/codex-deletions.log');
        @mkdir(dirname($this->log), 0777, true);
        @unlink($this->log);
    }

    protected function tearDown(): void
    {
        @unlink($this->log);

        parent::tearDown();
    }

    public function test_it_permanently_deletes_an_attachment(): void
    {
        $this->assertTrue(app(MediaRepository::class)->delete(10));
        $this->assertSame([['id' => 10, 'force' => true]], $this->deletions());
    }

    public function test_it_refuses_ids_that_are_not_attachments(): void
    {
        $media = app(MediaRepository::class);

        $this->assertFalse($media->delete(20));
        $this->assertFalse($media->delete(99));
        $this->assertFalse($media->delete(0));
        $this->assertSame([], $this->deletions());
    }

    public function test_it_reports_when_wordpress_fails_to_delete(): void
    {
        $this->assertFalse(app(MediaRepository::class)->delete(30));
        $this->assertSame([['id' => 30, 'force' => true]], $this->deletions());
    }

    /**
     * @return array<int, array{id: int, force: bool}>
     */
    private function deletions(): array
    {
        $lines = array_filter(explode("\n", (string) @file_get_contents($this->log)));

        return array_values(array_map(static fn (string $line): array => json_decode($line, true), $lines));
    }
}
