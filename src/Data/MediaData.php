<?php

declare(strict_types=1);

namespace Wordvel\Data;

use Spatie\LaravelData\Data;

class MediaData extends Data
{
    public function __construct(
        public int|string|null $id = null,
        public ?string $url = null,
        public ?string $mime_type = null,
        public ?string $title = null,
        public ?string $alt = null,
        public ?int $width = null,
        public ?int $height = null,
        public ?int $duration_seconds = null,
    ) {}
}
