<?php

declare(strict_types=1);

namespace App\Data;

use Langsys\OpenApiDocsGenerator\Generators\Attributes\Example;
use Wordvel\Blocks\Attributes\Select;
use Wordvel\Blocks\Attributes\Text;

/** @typescript */
final class ThemeLogoResource extends BaseData
{
    public function __construct(
        #[Select(['text' => 'Text'], 'Logo Type', required: true)]
        #[Example('text')]
        public string $type = 'text',
        #[Text('Logo Text', required: true)]
        #[Example('wordvel')]
        public string $text = 'wordvel',
        #[Text('Font Family', required: true)]
        #[Example('Righteous')]
        public string $font_family = 'Righteous',
    ) {}
}
