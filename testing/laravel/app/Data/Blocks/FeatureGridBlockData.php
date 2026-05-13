<?php

declare(strict_types=1);

namespace App\Data\Blocks;

use App\Data\BaseData;
use Illuminate\Support\Collection;
use Langsys\OpenApiDocsGenerator\Generators\Attributes\Example;
use Langsys\OpenApiDocsGenerator\Generators\Attributes\ItemType;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Wordvel\Blocks\Attributes\AsBlock;
use Wordvel\Blocks\Attributes\Repeater;
use Wordvel\Blocks\Attributes\Text;

/** @typescript */
#[AsBlock(key: 'feature-grid', name: 'Feature Grid', component: 'FeatureGrid')]
#[ItemType('gutenberg_block', handle: 'feature-grid')]
final class FeatureGridBlockData extends BaseData
{
    public function __construct(
        #[Text('Eyebrow')]
        #[Example('What WordVel gives you')]
        public ?string $eyebrow,
        #[Text('Heading', required: true)]
        #[Example('A clean bridge between content modeling and content editing.')]
        public string $heading,
        #[Repeater('Features', required: true)]
        #[DataCollectionOf(FeatureItemData::class)]
        public Collection $features,
    ) {}
}
