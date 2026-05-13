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
#[AsBlock(key: 'workflow', name: 'Workflow', component: 'Workflow')]
#[ItemType('gutenberg_block', handle: 'workflow')]
final class WorkflowBlockData extends BaseData
{
    public function __construct(
        #[Text('Eyebrow')]
        #[Example('Workflow')]
        public ?string $eyebrow,
        #[Text('Heading', required: true)]
        #[Example('From DTO to editor to API response.')]
        public string $heading,
        #[Text('Step 1 title')]
        public ?string $step_1_title,
        #[Textarea('Step 1 body')]
        public ?string $step_1_body,
        #[Text('Step 2 title')]
        public ?string $step_2_title,
        #[Textarea('Step 2 body')]
        public ?string $step_2_body,
        #[Text('Step 3 title')]
        public ?string $step_3_title,
        #[Textarea('Step 3 body')]
        public ?string $step_3_body,
        #[Text('Step 4 title')]
        public ?string $step_4_title,
        #[Textarea('Step 4 body')]
        public ?string $step_4_body,
    ) {}
}
