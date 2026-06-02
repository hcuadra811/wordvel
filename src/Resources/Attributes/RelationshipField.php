<?php

declare(strict_types=1);

namespace Wordvel\Resources\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class RelationshipField extends Field
{
    public function __construct(
        public string $resource,
        ?string $label = null,
        bool $required = false,
        bool $multiple = false,
        bool $sortable = false,
        ?string $column = null,
        ?string $relation = null,
        ?string $orderColumn = null,
        bool $bulkCreateFromMedia = false,
        string $bulkMediaType = 'audio',
        ?string $bulkTitleColumn = null,
        ?string $bulkSlugColumn = null,
        ?string $bulkMediaColumn = null,
        ?string $bulkUrlColumn = null,
        ?string $bulkDurationColumn = null,
        ?string $bulkOrderColumn = null,
        array $bulkCopyColumns = [],
    ) {
        parent::__construct('relationship', $label, $required, array_filter([
            'resource' => $resource,
            'multiple' => $multiple,
            'sortable' => $sortable,
            'column' => $column,
            'relation' => $relation,
            'order_column' => $orderColumn,
            'bulk_create_from_media' => $bulkCreateFromMedia,
            'bulk_media_type' => $bulkMediaType,
            'bulk_title_column' => $bulkTitleColumn,
            'bulk_slug_column' => $bulkSlugColumn,
            'bulk_media_column' => $bulkMediaColumn,
            'bulk_url_column' => $bulkUrlColumn,
            'bulk_duration_column' => $bulkDurationColumn,
            'bulk_order_column' => $bulkOrderColumn,
            'bulk_copy_columns' => $bulkCopyColumns,
        ], static fn (mixed $value): bool => $value !== null && $value !== false));
    }
}
