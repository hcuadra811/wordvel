<?php

declare(strict_types=1);

namespace Wordvel\Resources\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class RepeaterField extends Field
{
    public function __construct(?string $label = null, bool $required = false, ?string $of = null)
    {
        parent::__construct('repeater', $label, $required, array_filter([
            'of' => $of,
        ], static fn (mixed $value): bool => $value !== null));
    }
}
