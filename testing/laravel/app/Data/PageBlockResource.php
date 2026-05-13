<?php

declare(strict_types=1);

namespace App\Data;

use Langsys\OpenApiDocsGenerator\Generators\Attributes\Description;
use Langsys\OpenApiDocsGenerator\Generators\Attributes\Example;

/** @typescript */
final class PageBlockResource extends BaseData
{
    public function __construct(
        #[Example('hero-0')]
        public string $id,
        #[Example('hero')]
        public string $type,
        #[Example('Hero')]
        public string $component,
        #[Description('Block values. The PageBlock schema documents the possible concrete data DTOs with oneOf.')]
        public array $data,
    ) {}
}
