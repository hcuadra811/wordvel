<?php

declare(strict_types=1);

namespace Wordvel\Blocks;

use Wordvel\Data\BlockSchemaData;
use Wordvel\Data\FieldSchemaData;
use Wordvel\Regions\Fields\Field;

final class Block
{
    /**
     * @var Field[]
     */
    private array $fields = [];

    private function __construct(
        private readonly string $key,
        private readonly string $name,
        private readonly string $component,
    ) {}

    public static function make(string $key, string $name, string $component): self
    {
        return new self($key, $name, $component);
    }

    /**
     * @param Field[] $fields
     */
    public function fields(array $fields): self
    {
        $this->fields = $fields;

        return $this;
    }

    public function toData(): BlockSchemaData
    {
        return new BlockSchemaData(
            key: $this->key,
            name: $this->name,
            component: $this->component,
            fields: array_map(
                static fn (Field $field): FieldSchemaData => $field->toData(),
                $this->fields,
            ),
        );
    }
}
