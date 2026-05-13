<?php

declare(strict_types=1);

namespace App\Data\Blocks;

use App\Data\BaseData;
use Langsys\OpenApiDocsGenerator\Generators\Attributes\Description;
use Langsys\OpenApiDocsGenerator\Generators\Attributes\Example;
use Langsys\OpenApiDocsGenerator\Generators\Attributes\ItemType;

/** @typescript */
#[ItemType('gutenberg_block', handle: 'wordpress_block')]
final class WordPressBlockData extends BaseData
{
    public function __construct(
        #[Description('Original Gutenberg block name.'), Example('core/paragraph')]
        public string $name,
        #[Description('Original Gutenberg block attributes.')]
        public array $attrs,
        #[Description('Rendered/content HTML saved by WordPress for this block.'), Example('<p>Editorial paragraph from WordPress.</p>')]
        public string $html,
    ) {}
}
