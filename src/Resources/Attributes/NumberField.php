<?php

declare(strict_types=1);

namespace Wordvel\Resources\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class NumberField extends Field
{
    public function __construct(?string $label = null, bool $required = false, ?string $column = null, bool $readonly = false)
    {
        parent::__construct('number', $label, $required, array_filter([
            'column' => $column,
            'readonly' => $readonly,
        ], static fn (mixed $value): bool => $value !== null && $value !== false));
    }
}
