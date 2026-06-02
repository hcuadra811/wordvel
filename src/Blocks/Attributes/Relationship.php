<?php

declare(strict_types=1);

namespace Wordvel\Blocks\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class Relationship extends Field
{
    public function __construct(
        string $resource,
        ?string $label = null,
        bool $required = false,
        bool $multiple = false,
        bool $sortable = false,
    ) {
        parent::__construct('relationship', $label, $required, [
            'resource' => $resource,
            'multiple' => $multiple,
            'sortable' => $sortable,
        ]);
    }
}
