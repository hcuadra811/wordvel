<?php

declare(strict_types=1);

namespace Wordvel\Regions\Fields;

use Wordvel\Data\FieldSchemaData;

final class Field
{
    private mixed $default = null;

    private bool $required = false;

    /**
     * @var array<string, mixed>
     */
    private array $options = [];

    private function __construct(
        private readonly string $key,
        private readonly string $type,
        private readonly string $label,
    ) {}

    public static function make(string $key, string $type, string $label): self
    {
        return new self($key, $type, $label);
    }

    public static function text(string $key, string $label): self
    {
        return new self($key, 'text', $label);
    }

    public static function textarea(string $key, string $label): self
    {
        return new self($key, 'textarea', $label);
    }

    public static function richText(string $key, string $label): self
    {
        return new self($key, 'rich_text', $label);
    }

    public static function number(string $key, string $label): self
    {
        return new self($key, 'number', $label);
    }

    public static function boolean(string $key, string $label): self
    {
        return new self($key, 'boolean', $label);
    }

    /**
     * @param array<string, string> $options
     */
    public static function select(string $key, string $label, array $options): self
    {
        return (new self($key, 'select', $label))->options($options);
    }

    public static function url(string $key, string $label): self
    {
        return new self($key, 'url', $label);
    }

    public static function color(string $key, string $label): self
    {
        return new self($key, 'color', $label);
    }

    public static function image(string $key, string $label): self
    {
        return new self($key, 'image', $label);
    }

    public static function media(string $key, string $label): self
    {
        return new self($key, 'media', $label);
    }

    public function default(mixed $default): self
    {
        $this->default = $default;

        return $this;
    }

    public function required(bool $required = true): self
    {
        $this->required = $required;

        return $this;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function options(array $options): self
    {
        $this->options = $options;

        return $this;
    }

    public function toData(): FieldSchemaData
    {
        return new FieldSchemaData(
            key: $this->key,
            type: $this->type,
            label: $this->label,
            default: $this->default,
            required: $this->required,
            options: $this->options,
        );
    }
}
