<?php

declare(strict_types=1);

namespace Wordvel\ThemeOptions\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER | Attribute::TARGET_PROPERTY)]
final class OptionGroup
{
    public function __construct(
        public ?string $label = null,
    ) {}
}
