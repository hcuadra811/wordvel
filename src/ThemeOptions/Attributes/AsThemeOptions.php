<?php

declare(strict_types=1);

namespace Wordvel\ThemeOptions\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class AsThemeOptions
{
    public function __construct(
        public string $key,
        public string $name = 'Theme Options',
    ) {}
}
