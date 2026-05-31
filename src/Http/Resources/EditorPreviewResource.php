<?php

declare(strict_types=1);

namespace Wordvel\Http\Resources;

use Spatie\LaravelData\Data;

/**
 * Response DTO for the WordVel editor preview sync endpoint.
 */
class EditorPreviewResource extends Data
{
    public function __construct(
        public int $blocks,
        public int $css_bytes,
    ) {}
}
