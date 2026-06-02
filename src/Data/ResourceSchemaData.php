<?php

declare(strict_types=1);

namespace Wordvel\Data;

use Spatie\LaravelData\Data;

class ResourceSchemaData extends Data
{
    /**
     * @param FieldSchemaData[] $fields
     * @param array<string, mixed> $options
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $plural_label,
        public string $data_class,
        public string $model,
        public array $fields = [],
        public array $options = [],
    ) {}
}
