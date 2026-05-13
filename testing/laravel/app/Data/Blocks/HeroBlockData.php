<?php

declare(strict_types=1);

namespace App\Data\Blocks;

use App\Data\BaseData;
use App\Data\MediaResource;
use Langsys\OpenApiDocsGenerator\Generators\Attributes\Description;
use Langsys\OpenApiDocsGenerator\Generators\Attributes\Example;
use Langsys\OpenApiDocsGenerator\Generators\Attributes\ItemType;
use Wordvel\Blocks\Attributes\AsBlock;
use Wordvel\Blocks\Attributes\Image;
use Wordvel\Blocks\Attributes\Text;
use Wordvel\Blocks\Attributes\Textarea;
use Wordvel\Blocks\Attributes\Url;

/** @typescript */
#[AsBlock(key: 'hero', name: 'Hero', component: 'Hero')]
#[ItemType('gutenberg_block', handle: 'hero')]
final class HeroBlockData extends BaseData
{
    public function __construct(
        #[Text('Eyebrow')]
        #[Example('WordVel')]
        public ?string $eyebrow,
        #[Text('Headline', required: true)]
        #[Description('Primary headline value for the SPA hero component.'), Example('Build headless WordPress with Laravel')]
        public string $headline,
        #[Textarea('Subheadline')]
        #[Example('React receives structured content blocks from WordPress.')]
        public ?string $subheadline,
        #[Textarea('Body')]
        #[Example('WordVel gives teams a DTO-first way to model content, register editor fields, compose Gutenberg blocks, and ship clean API responses.')]
        public ?string $body,
        #[Image('Image')]
        public ?MediaResource $image,
        #[Text('Primary action label')]
        #[Example('Explore the architecture')]
        public ?string $primary_action_label,
        #[Url('Primary action URL')]
        #[Example('#architecture')]
        public ?string $primary_action_href,
        #[Text('Secondary action label')]
        #[Example('View API shape')]
        public ?string $secondary_action_label,
        #[Url('Secondary action URL')]
        #[Example('#api')]
        public ?string $secondary_action_href,
        #[Text('Stat 1 value')]
        #[Example('DTO')]
        public ?string $stat_1_value,
        #[Text('Stat 1 label')]
        #[Example('contracts drive docs, editor UI, and responses')]
        public ?string $stat_1_label,
        #[Text('Stat 2 value')]
        #[Example('WP')]
        public ?string $stat_2_value,
        #[Text('Stat 2 label')]
        #[Example('stays focused on content editing and storage')]
        public ?string $stat_2_label,
        #[Text('Stat 3 value')]
        #[Example('SPA')]
        public ?string $stat_3_value,
        #[Text('Stat 3 label')]
        #[Example('renders the experience with clean data')]
        public ?string $stat_3_label,
    ) {}
}
