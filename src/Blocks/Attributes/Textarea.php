<?php

declare(strict_types=1);

namespace Wordvel\Blocks\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class Textarea extends Field
{
    public function __construct(?string $label = null, bool $required = false)
    {
        parent::__construct('textarea', $label, $required);
    }
}
