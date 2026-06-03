<?php

declare(strict_types=1);

namespace Wordvel\Resources\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class TextField extends Field
{
    public function __construct(?string $label = null, bool $required = false, ?string $column = null, bool $localized = false)
    {
        parent::__construct('text', $label, $required, array_filter([
            'column' => $column,
            'localized' => $localized === true ? true : null,
        ], static fn (mixed $value): bool => $value !== null));
    }
}
