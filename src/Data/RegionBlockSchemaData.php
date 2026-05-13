<?php

declare(strict_types=1);

namespace Wordvel\Data;

use Spatie\LaravelData\Data;

final class RegionBlockSchemaData extends Data
{
    /**
     * @param FieldSchemaData[] $fields
     */
    public function __construct(
        public string $key,
        public string $type,
        public string $label,
        public array $fields = [],
        public bool $repeatable = false,
    ) {}
}
