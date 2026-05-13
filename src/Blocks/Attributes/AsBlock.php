<?php

declare(strict_types=1);

namespace Wordvel\Blocks\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class AsBlock
{
    public function __construct(
        public string $key,
        public string $name,
        public string $component,
    ) {}
}
