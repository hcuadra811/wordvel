<?php

declare(strict_types=1);

namespace App\Data\Blocks;

use App\Data\BaseData;
use Langsys\OpenApiDocsGenerator\Generators\Attributes\Description;
use Langsys\OpenApiDocsGenerator\Generators\Attributes\Example;
use Langsys\OpenApiDocsGenerator\Generators\Attributes\ItemType;
use Wordvel\Blocks\Attributes\AsBlock;
use Wordvel\Blocks\Attributes\RichText;

/** @typescript */
#[AsBlock(key: 'rich-text', name: 'Rich Text', component: 'RichText')]
#[ItemType('gutenberg_block', handle: 'rich-text')]
final class RichTextBlockData extends BaseData
{
    public function __construct(
        #[RichText('Body')]
        #[Description('Rich text content saved by Gutenberg and returned as content only.'), Example('This page is composed in WordPress using code-defined WordVel blocks.')]
        public ?string $body,
    ) {}
}
