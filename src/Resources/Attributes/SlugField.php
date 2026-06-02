<?php

declare(strict_types=1);

namespace Wordvel\Resources\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class SlugField extends Field
{
    public function __construct(?string $label = null, bool $required = false, ?string $from = null, ?string $column = null)
    {
        parent::__construct('slug', $label, $required, array_filter([
            'from' => $from,
            'column' => $column,
        ], static fn (mixed $value): bool => $value !== null));
    }
}
