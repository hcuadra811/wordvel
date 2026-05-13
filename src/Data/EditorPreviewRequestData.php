<?php

declare(strict_types=1);

namespace Wordvel\Data;

use Spatie\LaravelData\Data;

final class EditorPreviewRequestData extends Data
{
    /**
     * @param array<string, array{html: string}> $blocks
     */
    public function __construct(
        public array $blocks,
        public string $css,
    ) {}
}
