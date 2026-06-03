<?php

declare(strict_types=1);

namespace Wordvel\Resources;

use BackedEnum;
use Illuminate\Support\Collection;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Wordvel\Data\FieldSchemaData;
use Wordvel\Data\MediaData;
use Wordvel\Data\ResourceSchemaData;
use Wordvel\Resources\Attributes\Field;
use Wordvel\Resources\Attributes\MediaField;
use Wordvel\Resources\Attributes\OmitField;
use Wordvel\Resources\Attributes\RelationshipField;
use Wordvel\Resources\Attributes\WordVelResource;

final class ResourceSchemaFactory
{
    /**
     * @param class-string<Data> $class
     */
    public function forClass(string $class): ?ResourceSchemaData
    {
        $reflection = new ReflectionClass($class);
        $attribute = $reflection->getAttributes(WordVelResource::class)[0] ?? null;

        if ($attribute === null) {
            return null;
        }

        /** @var WordVelResource $resource */
        $resource = $attribute->newInstance();
        $key = $resource->key ?? str($reflection->getShortName())
            ->replaceEnd('Data', '')
            ->kebab()
            ->plural()
            ->toString();
        $label = $resource->label ?? str($reflection->getShortName())
            ->replaceEnd('Data', '')
            ->headline()
            ->toString();
        $pluralLabel = $resource->pluralLabel ?? str($label)->plural()->toString();
        $constructor = $reflection->getConstructor();

        return new ResourceSchemaData(
            key: $key,
            label: $label,
            plural_label: $pluralLabel,
            data_class: $class,
            model: $resource->model,
            fields: $constructor === null
                ? []
                : array_values(array_filter(array_map(
                    fn (ReflectionParameter $parameter): ?FieldSchemaData => $this->fieldSchema($parameter, $reflection, $resource),
                    $constructor->getParameters(),
                ))),
            options: [
                'title_field' => $resource->titleField,
                'order_by' => $resource->orderBy,
                'order_direction' => strtolower($resource->orderDirection) === 'desc' ? 'desc' : 'asc',
                'manual_fields' => $resource->manualFields,
                'menu_label' => $resource->menuLabel,
                'menu_icon' => $resource->menuIcon,
                'menu_position' => $resource->menuPosition,
                'search_fields' => array_values(array_filter($resource->searchFields, static fn (mixed $field): bool => is_string($field) && $field !== '')),
                'show_in_menu' => $resource->showInMenu,
                'show_in_overview' => $resource->showInOverview,
                'overview_order' => $resource->overviewOrder,
                'routable' => $resource->routable,
                'slug_prefix' => $resource->slugPrefix,
                'slug_field' => $resource->slugField,
            ],
        );
    }

    private function fieldSchema(ReflectionParameter $parameter, ReflectionClass $declaringClass, WordVelResource $resource): ?FieldSchemaData
    {
        if ($this->hasAttribute($parameter, $declaringClass, OmitField::class)) {
            return null;
        }

        $attribute = $this->fieldAttribute($parameter, $declaringClass);
        $field = $attribute?->newInstance();

        if ($field === null && $resource->manualFields) {
            return null;
        }

        $field ??= $this->inferField($parameter, $declaringClass);

        if ($field === null) {
            return null;
        }

        $type = $parameter->getType();
        $required = $field->required || ! $parameter->allowsNull();
        $default = $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null;
        $default = $default instanceof Collection ? [] : $default;
        $itemClass = $this->collectionItemClass($parameter, $declaringClass);
        $itemFields = $field->type === 'repeater' && $itemClass !== null ? $this->dataFields($itemClass) : [];
        $options = $this->normalizeOptions($field);

        if ($field->type === 'media' && $itemClass === MediaData::class) {
            $options['multiple'] = true;
        }

        return new FieldSchemaData(
            key: $parameter->getName(),
            type: $field->type,
            label: $field->label ?? str($parameter->getName())->replace('_', ' ')->headline()->toString(),
            default: $default,
            required: $required,
            options: $options,
            item_type: $itemClass,
            fields: $itemFields,
        );
    }

    /**
     * @return FieldSchemaData[]
     */
    private function dataFields(string $class): array
    {
        if (! class_exists($class) || ! is_subclass_of($class, Data::class)) {
            return [];
        }

        $reflection = new ReflectionClass($class);
        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return [];
        }

        $resource = new WordVelResource(model: '');

        return array_values(array_filter(array_map(
            fn (ReflectionParameter $parameter): ?FieldSchemaData => $this->fieldSchema($parameter, $reflection, $resource),
            $constructor->getParameters(),
        )));
    }

    private function fieldAttribute(ReflectionParameter $parameter, ReflectionClass $declaringClass): ?ReflectionAttribute
    {
        $attribute = $parameter->getAttributes(Field::class, ReflectionAttribute::IS_INSTANCEOF)[0] ?? null;

        if ($attribute !== null) {
            return $attribute;
        }

        if (! $declaringClass->hasProperty($parameter->getName())) {
            return null;
        }

        return $declaringClass->getProperty($parameter->getName())
            ->getAttributes(Field::class, ReflectionAttribute::IS_INSTANCEOF)[0] ?? null;
    }

    private function hasAttribute(ReflectionParameter $parameter, ReflectionClass $declaringClass, string $attributeClass): bool
    {
        if ($parameter->getAttributes($attributeClass) !== []) {
            return true;
        }

        return $declaringClass->hasProperty($parameter->getName())
            && $declaringClass->getProperty($parameter->getName())->getAttributes($attributeClass) !== [];
    }

    private function inferField(ReflectionParameter $parameter, ReflectionClass $declaringClass): ?Field
    {
        $type = $parameter->getType();

        if (! $type instanceof ReflectionNamedType) {
            return null;
        }

        $name = $type->getName();

        if ($name === 'array') {
            $itemClass = $this->collectionItemClass($parameter, $declaringClass);

            if ($itemClass === MediaData::class || is_a((string) $itemClass, MediaData::class, true)) {
                return new MediaField(multiple: true);
            }

            if (is_string($itemClass) && is_subclass_of($itemClass, Data::class)) {
                return new Field('repeater');
            }

            return null;
        }

        if ($type->isBuiltin()) {
            return match ($name) {
                'string' => new Field('text'),
                'int', 'float' => new Field('number'),
                'bool' => new Field('boolean'),
                default => null,
            };
        }

        if (enum_exists($name) && is_subclass_of($name, BackedEnum::class)) {
            return new Field('select', options: $this->enumOptions($name));
        }

        if (is_a($name, MediaData::class, true)) {
            return new MediaField();
        }

        if (is_a($name, Collection::class, true) || $name === 'array') {
            $itemClass = $this->collectionItemClass($parameter, $declaringClass);

            if ($itemClass === MediaData::class || is_a((string) $itemClass, MediaData::class, true)) {
                return new MediaField(multiple: true);
            }

            if (is_string($itemClass) && is_subclass_of($itemClass, Data::class)) {
                return new Field('repeater');
            }

            return null;
        }

        if (is_subclass_of($name, Data::class)) {
            return new Field('group');
        }

        return null;
    }

    /**
     * @return class-string<Data>|class-string<MediaData>|null
     */
    private function collectionItemClass(ReflectionParameter $parameter, ReflectionClass $declaringClass): ?string
    {
        $property = $declaringClass->hasProperty($parameter->getName())
            ? $declaringClass->getProperty($parameter->getName())
            : null;

        $attribute = $property?->getAttributes(DataCollectionOf::class)[0] ?? null;

        if ($attribute !== null) {
            /** @var DataCollectionOf $collection */
            $collection = $attribute->newInstance();

            return $collection->class;
        }

        $doc = $property?->getDocComment() ?: $parameter->getDeclaringFunction()->getDocComment() ?: '';

        if ($doc === '') {
            return null;
        }

        $patterns = [
            '/@var\s+(?:\\\\?Illuminate\\\\Support\\\\)?Collection\s*<\s*(?:int|string)\s*,\s*([\\\\A-Za-z0-9_]+)\s*>/',
            '/@var\s+(?:\\\\?Illuminate\\\\Support\\\\)?Collection\s*<\s*([\\\\A-Za-z0-9_]+)\s*>/',
            '/@var\s+([\\\\A-Za-z0-9_]+)\[\]/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $doc, $matches) !== 1) {
                continue;
            }

            return $this->resolveClass($matches[1], $declaringClass);
        }

        return null;
    }

    /**
     * @return class-string<Data>|class-string<MediaData>|null
     */
    private function resolveClass(string $class, ReflectionClass $declaringClass): ?string
    {
        $class = ltrim($class, '\\');

        if (class_exists($class)) {
            return $class;
        }

        $imports = $this->imports($declaringClass);

        if (isset($imports[$class]) && class_exists($imports[$class])) {
            return $imports[$class];
        }

        $namespaced = $declaringClass->getNamespaceName() . '\\' . $class;

        return class_exists($namespaced) ? $namespaced : null;
    }

    /**
     * @return array<string, class-string>
     */
    private function imports(ReflectionClass $declaringClass): array
    {
        $file = $declaringClass->getFileName();

        if (! is_string($file) || ! is_file($file)) {
            return [];
        }

        $source = (string) file_get_contents($file);
        preg_match_all('/^use\s+([^;]+);/m', $source, $matches);
        $imports = [];

        foreach ($matches[1] ?? [] as $import) {
            $import = trim($import);

            if (str_contains($import, ' function ') || str_contains($import, ' const ')) {
                continue;
            }

            [$class, $alias] = array_pad(preg_split('/\s+as\s+/i', $import), 2, null);
            $class = trim((string) $class);
            $shortName = $alias !== null ? trim($alias) : basename(str_replace('\\', '/', $class));

            if ($shortName !== '') {
                $imports[$shortName] = $class;
            }
        }

        return $imports;
    }

    /**
     * @param class-string<BackedEnum> $enum
     * @return array<string, string>
     */
    private function enumOptions(string $enum): array
    {
        $options = [];

        foreach ($enum::cases() as $case) {
            $options[(string) $case->value] = str($case->name)->headline()->toString();
        }

        return $options;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeOptions(Field $field): array
    {
        $options = $field->options;

        if (isset($options['options']) && is_string($options['options']) && enum_exists($options['options'])) {
            /** @var class-string<BackedEnum> $enum */
            $enum = $options['options'];
            $options['options'] = $this->enumOptions($enum);
        }

        if ($field instanceof RelationshipField) {
            $options['resource'] = $field->resource;
        }

        return $options;
    }
}
