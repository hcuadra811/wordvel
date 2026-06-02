<?php

declare(strict_types=1);

namespace Wordvel\Resources\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
class Field
{
    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        public string $type,
        public ?string $label = null,
        public bool $required = false,
        public array $options = [],
    ) {}
}
