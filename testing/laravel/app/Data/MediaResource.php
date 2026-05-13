<?php

declare(strict_types=1);

namespace App\Data;

use Langsys\OpenApiDocsGenerator\Generators\Attributes\Example;

/** @typescript */
final class MediaResource extends BaseData
{
    public function __construct(
        #[Example(12)]
        public int $id,
        #[Example('https://wordvel-wp.test/wp-content/uploads/hero.jpg')]
        public ?string $url,
        #[Example('image/jpeg')]
        public ?string $mime_type,
        #[Example('Hero image')]
        public ?string $title,
        #[Example('Editorial hero image')]
        public ?string $alt,
        #[Example(1600)]
        public ?int $width,
        #[Example(900)]
        public ?int $height,
    ) {}
}
