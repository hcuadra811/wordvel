<?php

declare(strict_types=1);

namespace Wordvel\Blocks\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class Icon extends Field
{
    /**
     * @param array<string, string> $icons
     */
    public function __construct(
        ?string $label = null,
        string $provider = 'lucide',
        array $icons = [],
        bool $required = false,
    ) {
        parent::__construct('icon', $label, $required, [
            'provider' => $provider,
            'icons' => $icons,
        ]);
    }
}
