<?php

declare(strict_types=1);

namespace App\Data\Blocks;

use App\Data\BaseData;
use Langsys\OpenApiDocsGenerator\Generators\Attributes\Example;
use Langsys\OpenApiDocsGenerator\Generators\Attributes\ItemType;
use Wordvel\Blocks\Attributes\AsBlock;
use Wordvel\Blocks\Attributes\Text;
use Wordvel\Blocks\Attributes\Textarea;

/** @typescript */
#[AsBlock(key: 'principles', name: 'Principles', component: 'Principles')]
#[ItemType('gutenberg_block', handle: 'principles')]
final class PrinciplesBlockData extends BaseData
{
    public function __construct(
        #[Text('Eyebrow')]
        #[Example('Architecture')]
        public ?string $eyebrow,
        #[Text('Heading', required: true)]
        #[Example('Laravel stays Laravel. WordPress stays useful.')]
        public string $heading,
        #[Text('Item 1 title')]
        public ?string $item_1_title,
        #[Textarea('Item 1 body')]
        public ?string $item_1_body,
        #[Text('Item 2 title')]
        public ?string $item_2_title,
        #[Textarea('Item 2 body')]
        public ?string $item_2_body,
        #[Text('Item 3 title')]
        public ?string $item_3_title,
        #[Textarea('Item 3 body')]
        public ?string $item_3_body,
    ) {}
}
