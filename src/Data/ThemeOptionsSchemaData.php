<?php

declare(strict_types=1);

namespace Wordvel\Data;

use Spatie\LaravelData\Data;

final class ThemeOptionsSchemaData extends Data
{
    /**
     * @param ThemeOptionGroupSchemaData[] $groups
     */
    public function __construct(
        public string $key,
        public string $name,
        public array $groups = [],
    ) {}
}
