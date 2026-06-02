<?php

declare(strict_types=1);

namespace Wordvel\Resources\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class MediaField extends Field
{
    public function __construct(
        public string $mediaType = 'image',
        ?string $label = null,
        bool $required = false,
        bool $multiple = false,
        ?string $column = null,
        ?string $urlColumn = null,
        ?string $durationColumn = null,
    ) {
        parent::__construct('media', $label, $required, array_filter([
            'media_type' => $mediaType,
            'multiple' => $multiple,
            'column' => $column,
            'url_column' => $urlColumn,
            'duration_column' => $durationColumn,
        ], static fn (mixed $value): bool => $value !== null && $value !== false));
    }
}
