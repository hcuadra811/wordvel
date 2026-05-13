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
#[AsBlock(key: 'api-preview', name: 'API Preview', component: 'ApiPreview')]
#[ItemType('gutenberg_block', handle: 'api-preview')]
final class ApiPreviewBlockData extends BaseData
{
    public function __construct(
        #[Text('Label')]
        #[Example('API payload')]
        public ?string $label,
        #[Text('Heading', required: true)]
        #[Example('React consumes content, not WordPress markup.')]
        public string $heading,
        #[Textarea('Body')]
        #[Example('The website can render blocks however it wants, while WordVel keeps editable values and API docs aligned with DTOs.')]
        public ?string $body,
        #[Textarea('Code sample')]
        public ?string $code,
    ) {}
}
