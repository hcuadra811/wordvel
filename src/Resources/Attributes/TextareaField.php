<?php

declare(strict_types=1);

namespace Wordvel\Resources\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class TextareaField extends Field
{
    public function __construct(?string $label = null, bool $required = false, ?string $column = null)
    {
        parent::__construct('rich_text', $label, $required, array_filter([
            'column' => $column,
        ], static fn (mixed $value): bool => $value !== null));
    }
}
