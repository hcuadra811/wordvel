<?php

declare(strict_types=1);

namespace Wordvel\Blocks\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class Select extends Field
{
    /**
     * @param array<string, string> $options
     */
    public function __construct(array $options, ?string $label = null, bool $required = false)
    {
        parent::__construct('select', $label, $required, $options);
    }
}
