<?php

declare(strict_types=1);

namespace Wordvel\Resources\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class SelectField extends Field
{
    /**
     * @param array<string, string>|class-string<\BackedEnum> $options
     */
    public function __construct(array|string $options, ?string $label = null, bool $required = false, ?string $column = null)
    {
        parent::__construct('select', $label, $required, array_filter([
            'options' => $options,
            'column' => $column,
        ], static fn (mixed $value): bool => $value !== null));
    }
}
