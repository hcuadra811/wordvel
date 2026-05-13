<?php

declare(strict_types=1);

namespace Wordvel\Data;

use Spatie\LaravelData\Data;

final class EditorPreviewResource extends Data
{
    public function __construct(
        public int $blocks,
        public int $css_bytes,
    ) {}
}
