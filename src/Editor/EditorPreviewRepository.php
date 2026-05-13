<?php

declare(strict_types=1);

namespace Wordvel\Editor;

use Illuminate\Support\Facades\File;
use Wordvel\Data\EditorPreviewRequestData;

final class EditorPreviewRepository
{
    public function store(EditorPreviewRequestData $data): void
    {
        File::ensureDirectoryExists($this->directory());

        File::put($this->previewPath(), json_encode([
            'blocks' => $data->blocks,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);

        File::put($this->cssPath(), $data->css);
    }

    /**
     * @return array{blocks: array<string, array{html: string}>}
     */
    public function preview(): array
    {
        if (! File::exists($this->previewPath())) {
            return ['blocks' => []];
        }

        $preview = json_decode((string) File::get($this->previewPath()), true);

        return is_array($preview) ? $preview : ['blocks' => []];
    }

    public function css(): string
    {
        return File::exists($this->cssPath()) ? (string) File::get($this->cssPath()) : '';
    }

    public function previewPath(): string
    {
        return $this->directory() . '/editor-preview.json';
    }

    public function cssPath(): string
    {
        return $this->directory() . '/editor.css';
    }

    private function directory(): string
    {
        return storage_path('wordvel/editor');
    }
}
