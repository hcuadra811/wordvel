<?php

declare(strict_types=1);

namespace Wordvel\Resources;

use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionUnionType;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Wordvel\Data\FieldSchemaData;
use Wordvel\Data\MediaData;
use Wordvel\Data\ResourceSchemaData;
use Wordvel\Resources\Attributes\Field;
use Wordvel\Resources\Attributes\IncludeField;
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
        $model = $resource->model ?? $this->inferModelClass($reflection);

        if ($model === null) {
            return null;
        }

        $baseName = $this->resourceBaseName($reflection);
        $key = $resource->key ?? $this->inferKey($reflection);
        $label = $resource->label ?? str($baseName)->headline()->toString();
        $pluralLabel = $resource->pluralLabel ?? str($label)->plural()->toString();
        $constructor = $reflection->getConstructor();
        $fields = $constructor === null
            ? []
            : array_values(array_filter(array_map(
                fn (ReflectionParameter $parameter): ?FieldSchemaData => $this->fieldSchema($parameter, $reflection, $resource),
                $constructor->getParameters(),
            )));
        $menuLabel = $resource->menuLabel ?? $this->inferMenuLabel($reflection);
        $orderBy = $resource->orderBy ?? $this->inferOrderBy($constructor, $model);

        return new ResourceSchemaData(
            key: $key,
            label: $label,
            plural_label: $pluralLabel,
            data_class: $class,
            model: $model,
            fields: $fields,
            options: [
                'title_field' => $resource->titleField ?? $this->inferTitleField($constructor, $model),
                'order_by' => $orderBy,
                'order_direction' => $resource->orderBy === null ? $this->inferOrderDirection($orderBy) : (strtolower($resource->orderDirection) === 'desc' ? 'desc' : 'asc'),
                'manual_fields' => $resource->manualFields,
                'menu_label' => $menuLabel,
                'menu_icon' => $resource->menuIcon ?? $this->inferMenuIcon($menuLabel),
                'menu_position' => $resource->menuPosition ?? $this->inferMenuPosition($menuLabel),
                'search_fields' => $resource->searchFields === []
                    ? $this->inferSearchFields($fields)
                    : array_values(array_filter($resource->searchFields, static fn (mixed $field): bool => is_string($field) && $field !== '')),
                'show_in_menu' => $resource->showInMenu,
                'show_in_overview' => $resource->showInOverview,
                'overview_order' => $resource->overviewOrder ?? $this->inferOverviewOrder($baseName),
                'routable' => $resource->routable,
                'slug_prefix' => $resource->slugPrefix ?? str($baseName)->kebab()->toString(),
                'slug_field' => $resource->slugField,
            ],
        );
    }

    private function fieldSchema(ReflectionParameter $parameter, ReflectionClass $declaringClass, WordVelResource $resource): ?FieldSchemaData
    {
        if ($this->hasAttribute($parameter, $declaringClass, OmitField::class)) {
            return null;
        }

        $model = $resource->model ?? $this->inferModelClass($declaringClass);
        $explicitField = $this->fieldAttribute($parameter, $declaringClass) !== null;

        if (! $explicitField && $this->shouldOmitByConvention($parameter, $declaringClass, $resource)) {
            return null;
        }

        $attribute = $this->fieldAttribute($parameter, $declaringClass);
        $field = $attribute?->newInstance();

        if ($field === null && $resource->manualFields) {
            return null;
        }

        $field ??= $this->inferField($parameter, $declaringClass, $resource, $model);

        if ($field === null) {
            return null;
        }

        if (! $explicitField && ! $this->isBackedByModel($parameter, $field, $model)) {
            return null;
        }

        $required = $field->required || $this->isRequiredParameter($parameter);
        $default = $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null;
        $default = $default instanceof Collection ? [] : $default;
        $itemClass = $this->collectionItemClass($parameter, $declaringClass);
        $itemFields = $field->type === 'repeater' && $itemClass !== null ? $this->dataFields($itemClass) : [];
        $options = $this->normalizeOptions($field);

        if (in_array($field->type, ['text', 'rich_text'], true) && $this->isLocalizedColumn($model, $options['column'] ?? $parameter->getName())) {
            $options['localized'] = true;
        }

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

        $resource = new WordVelResource(model: null, manualFields: false);

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

    private function inferField(ReflectionParameter $parameter, ReflectionClass $declaringClass, WordVelResource $resource, ?string $model): ?Field
    {
        $type = $parameter->getType();
        $name = $parameter->getName();
        $namedType = $this->namedType($parameter);
        $typeName = $namedType?->getName();
        $itemClass = $this->collectionItemClass($parameter, $declaringClass);

        if ($name === 'slug') {
            return new Field('slug', options: [
                'from' => $this->hasModelField($model, 'name') ? 'name' : null,
            ]);
        }

        if ($this->isMediaFieldName($name, $itemClass, $typeName)) {
            return $this->mediaFieldForName($name, $model);
        }

        $relationship = $this->relationshipFieldForName($parameter, $declaringClass, $resource, $model, $itemClass);

        if ($relationship instanceof Field) {
            return $relationship;
        }

        if (! $type instanceof ReflectionNamedType) {
            if ($type instanceof ReflectionUnionType && $this->unionBackedEnum($type) !== null) {
                return new Field('select', options: ['options' => $this->unionBackedEnum($type)]);
            }

            return null;
        }

        $typeName = $type->getName();

        if ($typeName === 'array') {
            if ($itemClass === MediaData::class || is_a((string) $itemClass, MediaData::class, true)) {
                return new MediaField(multiple: true);
            }

            if (is_string($itemClass) && is_subclass_of($itemClass, Data::class)) {
                return new Field('repeater');
            }

            return null;
        }

        if ($type->isBuiltin()) {
            return match ($typeName) {
                'string' => new Field($this->isTextareaName($name) ? 'rich_text' : 'text'),
                'int', 'float' => new Field('number'),
                'bool' => new Field('boolean'),
                default => null,
            };
        }

        if (enum_exists($typeName) && is_subclass_of($typeName, BackedEnum::class)) {
            return new Field('select', options: ['options' => $typeName]);
        }

        if (is_a($typeName, MediaData::class, true)) {
            return new MediaField();
        }

        if (is_a($typeName, Collection::class, true) || $typeName === 'array') {
            if ($itemClass === MediaData::class || is_a((string) $itemClass, MediaData::class, true)) {
                return new MediaField(multiple: true);
            }

            if (is_string($itemClass) && is_subclass_of($itemClass, Data::class)) {
                return new Field('repeater');
            }

            return null;
        }

        if (is_subclass_of($typeName, Data::class)) {
            return new Field('group');
        }

        return null;
    }

    private function shouldOmitByConvention(ReflectionParameter $parameter, ReflectionClass $declaringClass, WordVelResource $resource): bool
    {
        if ($this->hasAttribute($parameter, $declaringClass, IncludeField::class)) {
            return false;
        }

        $name = $parameter->getName();

        if ($name === 'id' && ! $resource->includeId) {
            return true;
        }

        return str_ends_with($name, '_at') && ! $resource->includeTimestamps;
    }

    private function isRequiredParameter(ReflectionParameter $parameter): bool
    {
        if ($parameter->allowsNull() || $parameter->isDefaultValueAvailable()) {
            return false;
        }

        $type = $parameter->getType();

        if ($type instanceof ReflectionNamedType) {
            return ! $this->isOptionalType($type->getName());
        }

        if ($type instanceof ReflectionUnionType) {
            foreach ($type->getTypes() as $unionType) {
                if ($unionType instanceof ReflectionNamedType && $this->isOptionalType($unionType->getName())) {
                    return false;
                }
            }
        }

        return true;
    }

    private function isOptionalType(string $type): bool
    {
        return $type === 'Spatie\\LaravelData\\Optional' || str_ends_with($type, '\\Optional');
    }

    private function namedType(ReflectionParameter $parameter): ?ReflectionNamedType
    {
        $type = $parameter->getType();

        return $type instanceof ReflectionNamedType ? $type : null;
    }

    /**
     * @return class-string<BackedEnum>|null
     */
    private function unionBackedEnum(ReflectionUnionType $type): ?string
    {
        foreach ($type->getTypes() as $unionType) {
            if (! $unionType instanceof ReflectionNamedType || $unionType->isBuiltin()) {
                continue;
            }

            $name = $unionType->getName();

            if (enum_exists($name) && is_subclass_of($name, BackedEnum::class)) {
                return $name;
            }
        }

        return null;
    }

    private function isTextareaName(string $name): bool
    {
        return str_contains($name, 'description')
            || str_contains($name, 'notes')
            || str_contains($name, 'address')
            || str_contains($name, 'reference');
    }

    private function isMediaFieldName(string $name, ?string $itemClass, ?string $typeName): bool
    {
        if (str_contains($name, 'image') || str_contains($name, 'media') || str_contains($name, 'gallery')) {
            return true;
        }

        return (is_string($typeName) && is_subclass_of($typeName, Data::class) && str_contains(class_basename($typeName), 'Media'))
            || (is_string($itemClass) && str_contains(class_basename($itemClass), 'Media'));
    }

    private function mediaFieldForName(string $name, ?string $model): Field
    {
        $multiple = str_contains($name, 'gallery') || str($name)->plural()->toString() === $name;
        $base = $multiple && str_ends_with($name, 'ies')
            ? (string) str($name)->replaceEnd('ies', 'y')
            : (string) str($name)->singular();
        $payloadColumn = $multiple ? $base . '_media' : $name;

        return new Field('media', options: array_filter([
            'media_type' => 'image',
            'multiple' => $multiple,
            'column' => $multiple ? $name . '_media_ids' : $name . '_media_id',
            'url_column' => $multiple ? null : $name . '_url',
            'payload_column' => $this->hasModelField($model, $payloadColumn) ? $payloadColumn : null,
        ], static fn (mixed $value): bool => $value !== null && $value !== false));
    }

    private function relationshipFieldForName(ReflectionParameter $parameter, ReflectionClass $declaringClass, WordVelResource $resource, ?string $model, ?string $itemClass): ?Field
    {
        if ($model === null || ! is_subclass_of($model, Model::class)) {
            return null;
        }

        $name = $parameter->getName();
        $modelInstance = new $model();

        if (str_ends_with($name, '_id')) {
            $relationName = str($name)->beforeLast('_id')->camel()->toString();
            $relation = $this->modelRelation($modelInstance, $relationName);

            if ($relation instanceof Relation) {
                return new Field('relationship', options: [
                    'resource' => $this->resourceKeyForModel($relation->getRelated()::class, $resource, $declaringClass),
                    'column' => $name,
                ]);
            }
        }

        if (method_exists($modelInstance, $name)) {
            $relation = $this->modelRelation($modelInstance, $name);

            if ($relation instanceof Relation) {
                return new Field('relationship', options: [
                    'resource' => $this->resourceKeyForModel($relation->getRelated()::class, $resource, $declaringClass),
                    'multiple' => true,
                    'relation' => $name,
                ]);
            }
        }

        if (is_string($itemClass) && is_subclass_of($itemClass, Data::class)) {
            $resourceKey = $this->resourceKeyForDataClass($itemClass);

            if ($resourceKey !== null && method_exists($modelInstance, $name)) {
                return new Field('relationship', options: [
                    'resource' => $resourceKey,
                    'multiple' => true,
                    'relation' => $name,
                ]);
            }
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

    private function resourceBaseName(ReflectionClass $reflection): string
    {
        return (string) str($reflection->getShortName())
            ->replaceEnd('Resource', '')
            ->replaceEnd('EditorData', '')
            ->replaceEnd('Data', '')
            ->toString();
    }

    private function inferKey(ReflectionClass $reflection): string
    {
        return str($this->resourceBaseName($reflection))->kebab()->plural()->toString();
    }

    private function inferMenuLabel(ReflectionClass $reflection): ?string
    {
        $namespace = $reflection->getNamespaceName();

        foreach (['\\Http\\Resources\\', '\\Data\\Resources\\'] as $segment) {
            if (! str_contains($namespace, $segment)) {
                continue;
            }

            $after = str($namespace)->after($segment)->before('\\')->toString();

            return $after !== '' ? str($after)->headline()->toString() : null;
        }

        return null;
    }

    private function inferMenuIcon(?string $menuLabel): ?string
    {
        return strtolower((string) $menuLabel) === 'shop' ? 'dashicons-cart' : null;
    }

    private function inferMenuPosition(?string $menuLabel): ?int
    {
        return strtolower((string) $menuLabel) === 'shop' ? 56 : null;
    }

    private function inferOverviewOrder(string $baseName): ?int
    {
        return match ($baseName) {
            'Product' => 10,
            'Category' => 20,
            'ProductAttribute' => 30,
            'Order' => 40,
            default => null,
        };
    }

    private function inferTitleField(?\ReflectionMethod $constructor, ?string $model): ?string
    {
        foreach (['name', 'title', 'number', 'label', 'product_name', 'value_text', 'slug'] as $candidate) {
            if ($constructor !== null && $this->constructorHasParameter($constructor, $candidate) && $this->hasModelField($model, $candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function inferOrderBy(?\ReflectionMethod $constructor, ?string $model): ?string
    {
        foreach (['position', 'sort_order', 'published_at', 'created_at', 'id'] as $candidate) {
            if ($constructor !== null && $this->constructorHasParameter($constructor, $candidate) && $this->hasModelField($model, $candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function inferOrderDirection(?string $orderBy): string
    {
        return in_array($orderBy, ['published_at', 'created_at'], true) ? 'desc' : 'asc';
    }

    /**
     * @param FieldSchemaData[] $fields
     * @return array<int, string>
     */
    private function inferSearchFields(array $fields): array
    {
        $searchable = [];

        foreach ($fields as $field) {
            if (! in_array($field->type, ['text', 'rich_text', 'slug'], true)) {
                continue;
            }

            if (preg_match('/(^|_)(name|title|slug|sku|code|number|email|phone|label)($|_)/', $field->key) !== 1) {
                continue;
            }

            $searchable[] = $field->key;
        }

        return array_values(array_unique($searchable));
    }

    private function constructorHasParameter(\ReflectionMethod $constructor, string $name): bool
    {
        foreach ($constructor->getParameters() as $parameter) {
            if ($parameter->getName() === $name) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return class-string<Model>|null
     */
    private function inferModelClass(ReflectionClass $reflection): ?string
    {
        if ($reflection->hasMethod('fromModel')) {
            $parameter = $reflection->getMethod('fromModel')->getParameters()[0] ?? null;
            $type = $parameter?->getType();

            if ($type instanceof ReflectionNamedType && ! $type->isBuiltin() && is_subclass_of($type->getName(), Model::class)) {
                return $type->getName();
            }
        }

        $baseName = $this->resourceBaseName($reflection);
        $namespace = $reflection->getNamespaceName();
        $candidates = [
            'App\\Models\\' . $baseName,
        ];

        if (str_contains($namespace, '\\Data\\Resources')) {
            $candidates[] = str_replace('\\Data\\Resources', '\\Models', $namespace) . '\\' . $baseName;
        }

        if (str_contains($namespace, '\\Http\\Resources')) {
            $candidates[] = str_replace('\\Http\\Resources', '\\Models', $namespace) . '\\' . $baseName;
        }

        foreach ($candidates as $candidate) {
            if (class_exists($candidate) && is_subclass_of($candidate, Model::class)) {
                return $candidate;
            }
        }

        return null;
    }

    private function hasModelField(?string $model, string $column): bool
    {
        if ($model === null || ! is_subclass_of($model, Model::class)) {
            return false;
        }

        $instance = new $model();

        if (in_array($column, $instance->getFillable(), true) || array_key_exists($column, $instance->getCasts())) {
            return true;
        }

        try {
            return Schema::connection($instance->getConnectionName())->hasColumn($instance->getTable(), $column);
        } catch (\Throwable) {
            return false;
        }
    }

    private function isBackedByModel(ReflectionParameter $parameter, Field $field, ?string $model): bool
    {
        if ($model === null || ! is_subclass_of($model, Model::class)) {
            return true;
        }

        $name = $parameter->getName();
        $options = $this->normalizeOptions($field);
        $instance = new $model();

        if ($field->type === 'relationship') {
            $relation = $options['relation'] ?? $name;

            if (is_string($relation) && method_exists($instance, $relation)) {
                return $this->modelRelation($instance, $relation) instanceof Relation;
            }

            $column = $options['column'] ?? $name;

            return is_string($column) && $this->hasModelField($model, $column);
        }

        if ($field->type === 'media') {
            $column = $options['column'] ?? $name;

            return is_string($column) && $this->hasModelField($model, $column);
        }

        $column = $options['column'] ?? $name;

        return is_string($column) && $this->hasModelField($model, $column);
    }

    private function isLocalizedColumn(?string $model, mixed $column): bool
    {
        if (! is_string($column) || $model === null || ! is_subclass_of($model, Model::class)) {
            return false;
        }

        $instance = new $model();
        $cast = $instance->getCasts()[$column] ?? null;

        if (in_array($cast, ['array', 'json', 'collection', 'object'], true)) {
            return true;
        }

        try {
            return in_array(Schema::connection($instance->getConnectionName())->getColumnType($instance->getTable(), $column), ['json', 'jsonb'], true);
        } catch (\Throwable) {
            return false;
        }
    }

    private function modelRelation(Model $model, string $name): ?Relation
    {
        if (! method_exists($model, $name)) {
            return null;
        }

        try {
            $relation = $model->{$name}();

            return $relation instanceof Relation ? $relation : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param class-string<Model> $model
     */
    private function resourceKeyForModel(string $model, WordVelResource $resource, ReflectionClass $declaringClass): string
    {
        $base = str(class_basename($model))->kebab()->plural()->toString();
        $current = $resource->key ?? str($this->resourceBaseName($declaringClass))->kebab()->plural()->toString();

        if (str_contains($current, '-')) {
            $prefix = str($current)->before('-')->toString();

            return $prefix !== '' ? $prefix . '-' . $base : $base;
        }

        return $base;
    }

    private function resourceKeyForDataClass(string $class): ?string
    {
        if (! is_subclass_of($class, Data::class)) {
            return null;
        }

        $reflection = new ReflectionClass($class);
        $attribute = $reflection->getAttributes(WordVelResource::class)[0] ?? null;

        if ($attribute !== null) {
            /** @var WordVelResource $resource */
            $resource = $attribute->newInstance();

            if ($resource->key !== null) {
                return $resource->key;
            }
        }

        return str($this->resourceBaseName($reflection))->kebab()->plural()->toString();
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
