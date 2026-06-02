<?php

declare(strict_types=1);

namespace Wordvel\Resources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Wordvel\Data\FieldSchemaData;
use Wordvel\Data\ResourceSchemaData;
use Wordvel\WordPress\MediaRepository;

final class ResourceRepository
{
    /**
     * @var array<int, array<string, mixed>|null>
     */
    private array $mediaCache = [];

    public function __construct(
        private readonly ResourceRegistry $registry,
        private readonly MediaRepository $media,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function index(ResourceSchemaData $schema, ?string $search = null, bool $summary = false, bool $summaryPreview = false): array
    {
        $model = $this->newModel($schema);
        $query = $model->newQuery();
        $titleField = $this->titleField($schema);
        $searchFields = $this->searchFields($schema);
        $searchTokens = $this->searchTokens($search);

        if ($searchTokens !== [] && $searchFields !== []) {
            $this->applyDatabaseSearch($query, $searchFields, $searchTokens);
        }

        $orderBy = $schema->options['order_by'] ?? null;

        if (is_string($orderBy) && $orderBy !== '') {
            $query->orderBy($orderBy, $schema->options['order_direction'] ?? 'asc');
        } elseif ($titleField !== null) {
            $query->orderBy($this->columnForField($titleField));
        } else {
            $query->latest();
        }

        $records = $query->limit($searchTokens === [] ? 200 : 500)->get();

        if ($searchTokens !== [] && $searchFields !== []) {
            $records = $this->rankSearchResults($records, $searchFields, (string) $search);

            if ($records->isEmpty()) {
                $fallbackQuery = $model->newQuery();

                if (is_string($orderBy) && $orderBy !== '') {
                    $fallbackQuery->orderBy($orderBy, $schema->options['order_direction'] ?? 'asc');
                } elseif ($titleField !== null) {
                    $fallbackQuery->orderBy($this->columnForField($titleField));
                } else {
                    $fallbackQuery->latest();
                }

                $records = $this->rankSearchResults($fallbackQuery->limit(500)->get(), $searchFields, (string) $search);
            }
        }

        return $records->take(200)
            ->map(fn (Model $model): array => $summary ? $this->summaryPayload($schema, $model, $summaryPreview) : $this->recordPayload($schema, $model))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(ResourceSchemaData $schema, int|string $id): ?array
    {
        $model = $this->newModel($schema)->newQuery()->find($id);

        return $model instanceof Model ? $this->recordPayload($schema, $model) : null;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function store(ResourceSchemaData $schema, array $payload): array
    {
        $model = $this->newModel($schema);

        return $this->persist($schema, $model, $payload);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>|null
     */
    public function update(ResourceSchemaData $schema, int|string $id, array $payload): ?array
    {
        $model = $this->newModel($schema)->newQuery()->find($id);

        if (! $model instanceof Model) {
            return null;
        }

        return $this->persist($schema, $model, $payload);
    }

    public function delete(ResourceSchemaData $schema, int|string $id): bool
    {
        $model = $this->newModel($schema)->newQuery()->find($id);

        return $model instanceof Model && (bool) $model->delete();
    }

    /**
     * @return array<int, array{id: string, label: string}>
     */
    public function options(string $resourceKey, ?string $search = null, array $ids = []): array
    {
        $schema = $this->registry->find($resourceKey);

        if ($schema === null) {
            return [];
        }

        $ids = array_values(array_filter(array_map(
            static fn (mixed $id): int => is_numeric($id) ? (int) $id : 0,
            $ids,
        )));

        if ($ids !== []) {
            return $this->newModel($schema)->newQuery()
                ->whereKey($ids)
                ->get()
                ->sortBy(static fn (Model $model): int => array_search((int) $model->getKey(), $ids, true))
                ->map(fn (Model $model): array => $this->summaryPayload($schema, $model))
                ->values()
                ->all();
        }

        return $this->index($schema, $search, summary: true);
    }

    /**
     * @param array<int, array<string, mixed>> $mediaItems
     * @return array<string, mixed>|null
     */
    public function createRelatedFromMedia(ResourceSchemaData $schema, int|string $id, string $fieldKey, array $mediaItems): ?array
    {
        $parent = $this->newModel($schema)->newQuery()->find($id);

        if (! $parent instanceof Model) {
            return null;
        }

        $field = collect($schema->fields)->first(static fn (FieldSchemaData $candidate): bool => $candidate->key === $fieldKey);

        if (! $field instanceof FieldSchemaData || ($field->options['bulk_create_from_media'] ?? false) !== true) {
            throw new \InvalidArgumentException("WordVel relationship [{$fieldKey}] does not allow bulk media creation.");
        }

        $relationName = $field->options['relation'] ?? $field->key;

        if (! is_string($relationName) || ! method_exists($parent, $relationName)) {
            throw new \InvalidArgumentException("WordVel relationship [{$fieldKey}] is not model-backed.");
        }

        $relation = $parent->{$relationName}();

        if (! $relation instanceof HasMany) {
            throw new \InvalidArgumentException("WordVel relationship [{$fieldKey}] must be a has-many relation for bulk media creation.");
        }

        $related = $relation->getRelated();
        $foreignKey = $relation->getForeignKeyName();
        $titleColumn = (string) ($field->options['bulk_title_column'] ?? 'title');
        $slugColumn = (string) ($field->options['bulk_slug_column'] ?? 'slug');
        $mediaColumn = (string) ($field->options['bulk_media_column'] ?? 'audio_media_id');
        $urlColumn = $field->options['bulk_url_column'] ?? null;
        $durationColumn = $field->options['bulk_duration_column'] ?? null;
        $orderColumn = $field->options['bulk_order_column'] ?? null;
        $copyColumns = is_array($field->options['bulk_copy_columns'] ?? null) ? $field->options['bulk_copy_columns'] : [];
        $nextOrder = is_string($orderColumn) && $orderColumn !== ''
            ? ((int) $relation->max($orderColumn)) + 1
            : null;

        foreach ($mediaItems as $media) {
            $title = $this->titleFromMedia($media);
            $attributes = [
                $foreignKey => $parent->getKey(),
                $titleColumn => $title,
                $slugColumn => $this->uniqueSlug($related, Str::slug($title)),
                $mediaColumn => $this->mediaId($media),
            ];

            foreach ($copyColumns as $column) {
                if (is_string($column) && $column !== '') {
                    $attributes[$column] = $parent->getAttribute($column);
                }
            }

            if (is_string($urlColumn) && $urlColumn !== '') {
                $attributes[$urlColumn] = is_array($media) ? ($media['url'] ?? null) : null;
            }

            if (is_string($durationColumn) && $durationColumn !== '') {
                $duration = is_array($media) ? ($media['duration_seconds'] ?? null) : null;
                $attributes[$durationColumn] = is_numeric($duration) ? (int) $duration : null;
            }

            if (is_string($orderColumn) && $orderColumn !== '') {
                $attributes[$orderColumn] = $nextOrder;
                $nextOrder++;
            }

            $related->newQuery()->create($attributes);
        }

        return $this->recordPayload($schema, $parent->refresh());
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>|null
     */
    public function createRelated(ResourceSchemaData $schema, int|string $id, string $fieldKey, array $payload): ?array
    {
        $parent = $this->newModel($schema)->newQuery()->find($id);

        if (! $parent instanceof Model) {
            return null;
        }

        $field = collect($schema->fields)->first(static fn (FieldSchemaData $candidate): bool => $candidate->key === $fieldKey);

        if (! $field instanceof FieldSchemaData) {
            throw new \InvalidArgumentException("WordVel relationship [{$fieldKey}] is not defined.");
        }

        $relationName = $field->options['relation'] ?? $field->key;

        if (! is_string($relationName) || ! method_exists($parent, $relationName)) {
            throw new \InvalidArgumentException("WordVel relationship [{$fieldKey}] is not model-backed.");
        }

        $relation = $parent->{$relationName}();

        if (! $relation instanceof HasMany) {
            throw new \InvalidArgumentException("WordVel relationship [{$fieldKey}] must be a has-many relation for inline child creation.");
        }

        $resourceKey = $field->options['resource'] ?? null;
        $targetSchema = is_string($resourceKey) ? $this->registry->find($resourceKey) : null;

        if (! $targetSchema instanceof ResourceSchemaData) {
            throw new \InvalidArgumentException("WordVel relationship [{$fieldKey}] does not point to a registered resource.");
        }

        $child = $relation->getRelated()->newInstance();
        $child->setAttribute($relation->getForeignKeyName(), $parent->getKey());

        $copyColumns = is_array($field->options['bulk_copy_columns'] ?? null) ? $field->options['bulk_copy_columns'] : [];

        foreach ($copyColumns as $column) {
            if (is_string($column) && $column !== '') {
                $child->setAttribute($column, $parent->getAttribute($column));
            }
        }

        $orderColumn = $field->options['bulk_order_column'] ?? null;

        if (is_string($orderColumn) && $orderColumn !== '') {
            $child->setAttribute($orderColumn, ((int) $relation->max($orderColumn)) + 1);
        }

        $managedColumns = array_values(array_filter(array_merge(
            [$relation->getForeignKeyName(), is_string($orderColumn) ? $orderColumn : null],
            array_filter($copyColumns, 'is_string'),
        )));

        if ($managedColumns !== []) {
            foreach ($targetSchema->fields as $targetField) {
                if (in_array($this->columnForField($targetField), $managedColumns, true)) {
                    Arr::forget($payload, $targetField->key);
                }
            }
        }

        $this->persist($targetSchema, $child, $payload);

        return $this->recordPayload($schema, $parent->refresh());
    }

    /**
     * @return FieldSchemaData[]
     */
    private function searchFields(ResourceSchemaData $schema): array
    {
        $configured = $schema->options['search_fields'] ?? [];

        if (is_array($configured) && $configured !== []) {
            return array_values(array_filter(array_map(
                fn (string $key): ?FieldSchemaData => collect($schema->fields)
                    ->first(static fn (FieldSchemaData $field): bool => $field->key === $key),
                array_values(array_filter($configured, 'is_string')),
            )));
        }

        $titleField = $this->titleField($schema);
        $fields = [];

        if ($titleField instanceof FieldSchemaData) {
            $fields[$titleField->key] = $titleField;
        }

        foreach ($schema->fields as $field) {
            if (! in_array($field->type, ['text', 'slug'], true)) {
                continue;
            }

            if (in_array($field->key, ['title', 'name', 'slug'], true) || str_ends_with($field->key, '_slug')) {
                $fields[$field->key] = $field;
            }
        }

        return array_values($fields);
    }

    /**
     * @return array<int, string>
     */
    private function searchTokens(?string $search): array
    {
        $normalized = $this->normalizeSearchText((string) $search);

        if ($normalized === '') {
            return [];
        }

        return array_values(array_unique(array_filter(
            preg_split('/\s+/', $normalized) ?: [],
            static fn (string $token): bool => $token !== '',
        )));
    }

    /**
     * @param FieldSchemaData[] $fields
     * @param array<int, string> $tokens
     */
    private function applyDatabaseSearch(mixed $query, array $fields, array $tokens): void
    {
        foreach ($tokens as $token) {
            $query->where(function ($tokenQuery) use ($fields, $token): void {
                foreach ($fields as $field) {
                    $tokenQuery->orWhere($this->columnForField($field), 'like', '%' . $token . '%');
                }
            });
        }
    }

    /**
     * @param Collection<int, Model> $records
     * @param FieldSchemaData[] $fields
     * @return Collection<int, Model>
     */
    private function rankSearchResults(Collection $records, array $fields, string $search): Collection
    {
        $needle = $this->normalizeSearchText($search);
        $tokens = $this->searchTokens($search);

        if ($needle === '' || $tokens === []) {
            return $records;
        }

        return $records
            ->map(fn (Model $model): array => [$model, $this->searchScore($model, $fields, $needle, $tokens)])
            ->filter(static fn (array $result): bool => $result[1] > 0)
            ->sortByDesc(static fn (array $result): int => $result[1])
            ->map(static fn (array $result): Model => $result[0])
            ->values();
    }

    /**
     * @param FieldSchemaData[] $fields
     * @param array<int, string> $tokens
     */
    private function searchScore(Model $model, array $fields, string $needle, array $tokens): int
    {
        $fieldValues = array_map(
            fn (FieldSchemaData $field): string => $this->normalizeSearchText((string) $model->getAttribute($this->columnForField($field))),
            $fields,
        );
        $combined = implode(' ', array_filter($fieldValues));

        if ($combined === '') {
            return 0;
        }

        $allTokensPresent = collect($tokens)->every(static fn (string $token): bool => str_contains($combined, $token));

        if (! $allTokensPresent) {
            return 0;
        }

        $score = 50;

        foreach ($fieldValues as $fieldValue) {
            if ($fieldValue === $needle) {
                $score += 100;
            } elseif (str_starts_with($fieldValue, $needle)) {
                $score += 80;
            } elseif (str_contains($fieldValue, $needle)) {
                $score += 60;
            }
        }

        foreach ($tokens as $token) {
            foreach ($fieldValues as $fieldValue) {
                if ($fieldValue === $token) {
                    $score += 30;
                } elseif (str_starts_with($fieldValue, $token)) {
                    $score += 20;
                } elseif (str_contains($fieldValue, $token)) {
                    $score += 10;
                }
            }
        }

        return $score;
    }

    private function normalizeSearchText(string $value): string
    {
        $value = Str::of($value)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->squish()
            ->toString();

        return trim($value);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function persist(ResourceSchemaData $schema, Model $model, array $payload): array
    {
        $afterSave = [];

        foreach ($schema->fields as $field) {
            $value = Arr::get($payload, $field->key);

            if ($field->type === 'slug' && ($value === null || $value === '')) {
                $from = $field->options['from'] ?? null;
                $value = is_string($from) ? Str::slug((string) Arr::get($payload, $from, '')) : '';
            }

            $deferred = $this->assignField($model, $field, $value);

            if ($deferred !== null) {
                $afterSave[] = $deferred;
            }
        }

        $model->save();

        foreach ($afterSave as $callback) {
            $callback($model);
        }

        return $this->recordPayload($schema, $model->refresh());
    }

    private function assignField(Model $model, FieldSchemaData $field, mixed $value): ?callable
    {
        if (($field->options['readonly'] ?? false) === true) {
            return null;
        }

        if ($field->type === 'media') {
            $this->assignMediaField($model, $field, $value);

            return null;
        }

        if ($field->type === 'relationship') {
            return $this->assignRelationshipField($model, $field, $value);
        }

        $column = $this->columnForField($field);

        if ($field->type === 'number') {
            $model->setAttribute($column, is_numeric($value) ? $value + 0 : null);

            return null;
        }

        if ($field->type === 'boolean') {
            $model->setAttribute($column, $value === true || $value === '1' || $value === 1);

            return null;
        }

        if ($field->type !== 'repeater' && $field->type !== 'group') {
            $model->setAttribute($column, $value);
        }

        return null;
    }

    private function assignMediaField(Model $model, FieldSchemaData $field, mixed $value): void
    {
        $column = $this->columnForField($field);

        if (($field->options['multiple'] ?? false) === true) {
            $model->setAttribute($column, array_values(array_filter(array_map(
                fn (mixed $item): int => $this->mediaId($item),
                is_array($value) ? $value : [],
            ))));

            return;
        }

        $mediaId = $this->mediaId($value);
        $model->setAttribute($column, $mediaId > 0 ? $mediaId : null);

        $urlColumn = $field->options['url_column'] ?? null;

        if (is_string($urlColumn) && $urlColumn !== '') {
            $model->setAttribute($urlColumn, is_array($value) ? ($value['url'] ?? null) : null);
        }

        $durationColumn = $field->options['duration_column'] ?? null;

        if (is_string($durationColumn) && $durationColumn !== '') {
            $duration = is_array($value) ? ($value['duration_seconds'] ?? null) : null;
            $model->setAttribute($durationColumn, is_numeric($duration) ? (int) $duration : $model->getAttribute($durationColumn));
        }
    }

    private function assignRelationshipField(Model $model, FieldSchemaData $field, mixed $value): ?callable
    {
        $ids = array_values(array_filter(array_map(
            static fn (mixed $id): int => is_numeric($id) ? (int) $id : 0,
            is_array($value) ? $value : ($value === null || $value === '' ? [] : [$value]),
        )));

        if (($field->options['multiple'] ?? false) !== true) {
            $column = $this->columnForField($field);
            $model->setAttribute($column, $ids[0] ?? null);

            return null;
        }

        $column = $field->options['column'] ?? null;

        if (is_string($column) && $column !== '') {
            $model->setAttribute($column, $ids);

            return null;
        }

        $relationName = $field->options['relation'] ?? $field->key;

        if (! is_string($relationName) || ! method_exists($model, $relationName)) {
            return null;
        }

        return static function (Model $savedModel) use ($relationName, $ids): void {
            $relation = $savedModel->{$relationName}();

            if ($relation instanceof BelongsToMany) {
                $relation->sync($ids);

                return;
            }

            if (! $relation instanceof HasMany) {
                return;
            }

            $related = $relation->getRelated();
            $foreignKey = $relation->getForeignKeyName();
            $related->newQuery()
                ->where($foreignKey, $savedModel->getKey())
                ->when($ids !== [], fn ($query) => $query->whereNotIn($related->getKeyName(), $ids))
                ->update([$foreignKey => null]);

            foreach ($ids as $index => $id) {
                $attributes = [$foreignKey => $savedModel->getKey()];

                $orderColumn = $this->relationshipOrderColumn($field, $related);

                if ($orderColumn !== null) {
                    $attributes[$orderColumn] = $index + 1;
                }

                $related->newQuery()->whereKey($id)->update($attributes);
            }
        };
    }

    private function relationshipOrderColumn(FieldSchemaData $field, Model $related): ?string
    {
        $configured = $field->options['order_column'] ?? $field->options['bulk_order_column'] ?? null;

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        foreach (['sort_order', 'position', 'order', 'track_number'] as $candidate) {
            if (in_array($candidate, $related->getFillable(), true)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function recordPayload(ResourceSchemaData $schema, Model $model): array
    {
        return [
            'id' => (string) $model->getKey(),
            'title' => $this->recordTitle($schema, $model),
            'values' => collect($schema->fields)
                ->mapWithKeys(fn (FieldSchemaData $field): array => [$field->key => $this->fieldValue($model, $field)])
                ->all(),
        ];
    }

    /**
     * @return array{id: string, label: string, title: string, preview?: array<string, mixed>|null}
     */
    private function summaryPayload(ResourceSchemaData $schema, Model $model, bool $includePreview = false): array
    {
        $title = $this->recordTitle($schema, $model);

        $payload = [
            'id' => (string) $model->getKey(),
            'label' => $title,
            'title' => $title,
        ];

        if ($includePreview) {
            $payload['preview'] = $this->summaryPreview($schema, $model);
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function summaryPreview(ResourceSchemaData $schema, Model $model): ?array
    {
        foreach ($schema->fields as $field) {
            if ($field->type !== 'media' || ($field->options['media_type'] ?? null) !== 'image') {
                continue;
            }

            $value = $this->fieldValue($model, $field);
            $preview = ($field->options['multiple'] ?? false) === true
                ? (is_array($value) ? ($value[0] ?? null) : null)
                : $value;

            if (is_array($preview) && is_string($preview['url'] ?? null) && $preview['url'] !== '') {
                return $preview;
            }
        }

        return null;
    }

    private function fieldValue(Model $model, FieldSchemaData $field): mixed
    {
        if ($field->type === 'media') {
            if (($field->options['multiple'] ?? false) === true) {
                $ids = $model->getAttribute($this->columnForField($field));

                return array_values(array_filter(array_map(
                    fn (mixed $id): ?array => is_numeric($id) ? $this->mediaPayload((int) $id) : null,
                    is_array($ids) ? $ids : [],
                )));
            }

            $id = $model->getAttribute($this->columnForField($field));
            $fallbackUrl = $field->options['url_column'] ?? null;

            return is_numeric($id) ? $this->mediaPayload((int) $id, is_string($fallbackUrl) ? $model->getAttribute($fallbackUrl) : null) : null;
        }

        if ($field->type === 'relationship') {
            return $this->relationshipValue($model, $field);
        }

        return $model->getAttribute($this->columnForField($field));
    }

    private function relationshipValue(Model $model, FieldSchemaData $field): mixed
    {
        $relationName = $field->options['relation'] ?? $field->key;
        $column = $field->options['column'] ?? null;

        if (($field->options['multiple'] ?? false) === true) {
            if (is_string($column) && $column !== '') {
                $value = $model->getAttribute($column);

                return is_array($value) ? array_map('strval', $value) : [];
            }

            if (is_string($relationName) && method_exists($model, $relationName)) {
                return $model->{$relationName}()->get()->pluck($model->{$relationName}()->getRelated()->getKeyName())
                    ->map(static fn (mixed $id): string => (string) $id)
                    ->values()
                    ->all();
            }

            return [];
        }

        return (string) $model->getAttribute($this->columnForField($field));
    }

    private function mediaPayload(int $id, ?string $fallbackUrl = null): ?array
    {
        if (array_key_exists($id, $this->mediaCache)) {
            $payload = $this->mediaCache[$id];

            if ($payload === null && $fallbackUrl !== null) {
                return [
                    'id' => $id,
                    'url' => $fallbackUrl,
                    'mime_type' => null,
                    'title' => null,
                    'alt' => null,
                    'width' => null,
                    'height' => null,
                    'duration_seconds' => null,
                ];
            }

            return $payload;
        }

        $media = $this->media->find($id);

        if ($media === null && $fallbackUrl === null) {
            $this->mediaCache[$id] = null;

            return null;
        }

        return $this->mediaCache[$id] = [
            'id' => $id,
            'url' => $media?->url ?? $fallbackUrl,
            'mime_type' => $media?->mimeType,
            'title' => $media?->title,
            'alt' => $media?->alt,
            'width' => $media?->width,
            'height' => $media?->height,
            'duration_seconds' => $media?->durationSeconds,
        ];
    }

    private function mediaId(mixed $value): int
    {
        if (is_numeric($value)) {
            return (int) $value;
        }

        if (is_array($value) && is_numeric($value['id'] ?? null)) {
            return (int) $value['id'];
        }

        return 0;
    }

    private function columnForField(FieldSchemaData $field): string
    {
        $column = $field->options['column'] ?? null;

        if (is_string($column) && $column !== '') {
            return $column;
        }

        if ($field->type === 'media') {
            return ($field->options['multiple'] ?? false) === true
                ? Str::singular($field->key) . '_media_ids'
                : $field->key . '_media_id';
        }

        if ($field->type === 'relationship' && ($field->options['multiple'] ?? false) !== true) {
            return str_ends_with($field->key, '_id') ? $field->key : $field->key . '_id';
        }

        return $field->key;
    }

    private function recordTitle(ResourceSchemaData $schema, Model $model): string
    {
        $titleField = $this->titleField($schema);

        if ($titleField !== null) {
            return (string) $model->getAttribute($this->columnForField($titleField));
        }

        return '#' . (string) $model->getKey();
    }

    /**
     * @param array<string, mixed> $media
     */
    private function titleFromMedia(array $media): string
    {
        $title = (string) ($media['title'] ?? '');

        if ($title !== '') {
            return str($title)->replace(['-', '_'], ' ')->squish()->title()->toString();
        }

        $path = parse_url((string) ($media['url'] ?? ''), PHP_URL_PATH);
        $basename = $path !== null ? pathinfo($path, PATHINFO_FILENAME) : 'Untitled track';

        return str($basename)->replace(['-', '_'], ' ')->squish()->title()->toString();
    }

    private function uniqueSlug(Model $model, string $baseSlug): string
    {
        $slug = $baseSlug !== '' ? $baseSlug : 'item';
        $candidate = $slug;
        $index = 2;

        while ($model->newQuery()->where('slug', $candidate)->exists()) {
            $candidate = $slug . '-' . $index;
            $index++;
        }

        return $candidate;
    }

    private function titleField(ResourceSchemaData $schema): ?FieldSchemaData
    {
        $configured = $schema->options['title_field'] ?? null;

        if (is_string($configured) && $configured !== '') {
            foreach ($schema->fields as $field) {
                if ($field->key === $configured) {
                    return $field;
                }
            }
        }

        foreach (['title', 'name', 'headline'] as $key) {
            foreach ($schema->fields as $field) {
                if ($field->key === $key) {
                    return $field;
                }
            }
        }

        return $schema->fields[0] ?? null;
    }

    private function newModel(ResourceSchemaData $schema): Model
    {
        $model = app($schema->model);

        if (! $model instanceof Model) {
            throw new \RuntimeException("WordVel resource model [{$schema->model}] must be an Eloquent model.");
        }

        return $model;
    }
}
