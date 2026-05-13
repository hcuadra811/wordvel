<?php

declare(strict_types=1);

namespace App\Data\Blocks;

use App\Data\BaseData;
use Wordvel\Blocks\Attributes\Icon;
use Wordvel\Blocks\Attributes\Text;
use Wordvel\Blocks\Attributes\Textarea;

/** @typescript */
final class FeatureItemData extends BaseData
{
    public function __construct(
        #[Icon('Icon', icons: [
            'PanelsTopLeft' => 'Page layout',
            'Blocks' => 'Blocks',
            'Braces' => 'Braces',
            'DatabaseZap' => 'Database',
            'Route' => 'Route',
            'FileCode2' => 'Code file',
        ])]
        public ?string $icon,
        #[Text('Title', required: true)]
        public string $title,
        #[Textarea('Body')]
        public ?string $body,
    ) {}
}
