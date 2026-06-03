<?php

declare(strict_types=1);

namespace Wordvel\Resources\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class RepeaterField extends Field
{
    public function __construct(
        ?string $label = null,
        bool $required = false,
        ?string $of = null,
        bool $sortable = false,
        ?int $maxVisibleRows = null,
        bool $insertBetween = false,
    ) {
        parent::__construct('repeater', $label, $required, array_filter([
            'of' => $of,
            'sortable' => $sortable,
            'max_visible_rows' => $maxVisibleRows,
            'insert_between' => $insertBetween === true ? true : null,
        ], static fn (mixed $value): bool => $value !== null && $value !== false));
    }
}
