<?php

declare(strict_types=1);

namespace Wordvel\Data;

use Spatie\LaravelData\Data;

final class FieldSchemaData extends Data
{
    /**
     * @param array<string, mixed> $options
     * @param FieldSchemaData[] $fields
     */
    public function __construct(
        public string $key,
        public string $type,
        public string $label,
        public mixed $default = null,
        public bool $required = false,
        public array $options = [],
        public ?string $item_type = null,
        public array $fields = [],
    ) {}
}
