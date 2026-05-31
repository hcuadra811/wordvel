<?php

declare(strict_types=1);

namespace Wordvel\Http\Requests;

use Spatie\LaravelData\Data;

/**
 * Request DTO for the WordVel editor preview sync endpoint.
 * Used by frontend adapters (@wordvel/react, etc.) to send rendered placeholder content.
 */
class EditorPreviewRequest extends Data
{
    /**
     * @param array<string, array{html: string}> $blocks
     */
    public function __construct(
        public array $blocks,
        public string $css,
    ) {}
}
