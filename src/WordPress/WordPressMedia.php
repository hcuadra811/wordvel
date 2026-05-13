<?php

declare(strict_types=1);

namespace Wordvel\WordPress;

final class WordPressMedia
{
    public function __construct(
        public readonly int $id,
        public readonly ?string $url,
        public readonly ?string $mimeType,
        public readonly ?string $title,
        public readonly ?string $alt,
        public readonly ?int $width,
        public readonly ?int $height,
    ) {}
}
