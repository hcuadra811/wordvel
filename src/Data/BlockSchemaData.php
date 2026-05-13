<?php

declare(strict_types=1);

namespace Wordvel\Data;

use Spatie\LaravelData\Data;

final class BlockSchemaData extends Data
{
    /**
     * @param FieldSchemaData[] $fields
     */
    public function __construct(
        public string $key,
        public string $name,
        public string $component,
        public string $data_class,
        public array $fields = [],
    ) {}
}
