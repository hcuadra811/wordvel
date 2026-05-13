<?php

declare(strict_types=1);

namespace Wordvel\Data;

use Spatie\LaravelData\Data;

final class ThemeOptionGroupSchemaData extends Data
{
    /**
     * @param FieldSchemaData[] $fields
     */
    public function __construct(
        public string $key,
        public string $label,
        public array $fields = [],
    ) {}
}
