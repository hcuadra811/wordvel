<?php

declare(strict_types=1);

namespace Wordvel\Regions\Blocks;

use Wordvel\Data\FieldSchemaData;
use Wordvel\Data\RegionBlockSchemaData;
use Wordvel\Regions\Fields\Field;

final class Block
{
    /**
     * @var Field[]
     */
    private array $fields = [];

    private bool $repeatable = false;

    private function __construct(
        private readonly string $key,
        private readonly string $type,
        private readonly string $label,
    ) {}

    public static function make(string $key, string $type, string $label): self
    {
        return new self($key, $type, $label);
    }

    public static function logo(string $key = 'logo', string $label = 'Logo'): self
    {
        return new self($key, 'logo', $label);
    }

    public static function menu(string $key, string $label = 'Menu', string $location = 'header'): self
    {
        return (new self($key, 'menu', $label))->fields([
            Field::make('location', 'menu_location', 'Menu Location')
                ->default($location)
                ->required(),
        ]);
    }

    public static function text(string $key, string $label = 'Text', string $default = ''): self
    {
        return (new self($key, 'text', $label))->fields([
            Field::make('text', 'text', 'Text')->default($default),
        ]);
    }

    public static function link(string $key, string $label = 'Link'): self
    {
        return (new self($key, 'link', $label))->fields([
            Field::make('label', 'text', 'Label')->required(),
            Field::make('url', 'url', 'URL')->required(),
        ]);
    }

    /**
     * @param Field[] $fields
     */
    public function fields(array $fields): self
    {
        $this->fields = $fields;

        return $this;
    }

    public function repeatable(bool $repeatable = true): self
    {
        $this->repeatable = $repeatable;

        return $this;
    }

    public function toData(): RegionBlockSchemaData
    {
        return new RegionBlockSchemaData(
            key: $this->key,
            type: $this->type,
            label: $this->label,
            fields: array_map(
                static fn (Field $field): FieldSchemaData => $field->toData(),
                $this->fields,
            ),
            repeatable: $this->repeatable,
        );
    }
}
