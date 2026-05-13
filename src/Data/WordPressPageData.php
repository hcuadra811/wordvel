<?php

declare(strict_types=1);

namespace Wordvel\Data;

use Langsys\OpenApiDocsGenerator\Generators\Attributes\Description;
use Langsys\OpenApiDocsGenerator\Generators\Attributes\Example;
use Langsys\OpenApiDocsGenerator\Generators\Attributes\OneOfItemsFrom;
use Spatie\LaravelData\Data;

abstract class WordPressPageData extends Data
{
    public function __construct(
        #[Description('WordPress post ID.'), Example(6)]
        public int $id,
        #[Description('WordPress page slug.'), Example('home')]
        public string $slug,
        #[Description('WordPress page title.'), Example('Home')]
        public string $title,
        #[Description('WordPress post status.'), Example('publish')]
        public string $status,
        #[OneOfItemsFrom('gutenberg_block')]
        #[Description('Ordered content blocks parsed from WordPress post_content.')]
        public array $blocks,
    ) {}
}
