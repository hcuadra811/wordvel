<?php

declare(strict_types=1);

namespace Wordvel\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Wordvel\Resources\ResourceRegistry;
use Wordvel\Resources\ResourceRepository;
use Wordvel\Support\LocalizedValue;

final class ResourceController
{
    public function __construct(
        private readonly ResourceRegistry $resources,
        private readonly ResourceRepository $repository,
    ) {}

    public function schemas(): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => array_map(
                static fn ($schema): array => $schema->toArray(),
                $this->resources->all(),
            ),
        ]);
    }

    public function index(Request $request, string $resource): JsonResponse
    {
        $schema = $this->resources->find($resource);

        abort_if($schema === null, 404, 'WordVel resource not found.');

        return response()->json([
            'status' => true,
            'data' => $this->repository->index(
                $schema,
                $request->string('search')->toString(),
                $request->boolean('summary'),
                $request->boolean('summary_preview'),
                LocalizedValue::languageFromRequest($request),
            ),
        ]);
    }

    public function store(Request $request, string $resource): JsonResponse
    {
        $schema = $this->resources->find($resource);

        abort_if($schema === null, 404, 'WordVel resource not found.');

        return response()->json([
            'status' => true,
            'data' => $this->repository->store($schema, $request->input('values', []), LocalizedValue::languageFromRequest($request)),
        ], 201);
    }

    public function show(Request $request, string $resource, int|string $id): JsonResponse
    {
        $schema = $this->resources->find($resource);

        abort_if($schema === null, 404, 'WordVel resource not found.');

        $record = $this->repository->find($schema, $id, LocalizedValue::languageFromRequest($request));

        abort_if($record === null, 404, 'WordVel resource record not found.');

        return response()->json([
            'status' => true,
            'data' => $record,
        ]);
    }

    public function update(Request $request, string $resource, int|string $id): JsonResponse
    {
        $schema = $this->resources->find($resource);

        abort_if($schema === null, 404, 'WordVel resource not found.');

        $record = $this->repository->update($schema, $id, $request->input('values', []), LocalizedValue::languageFromRequest($request));

        abort_if($record === null, 404, 'WordVel resource record not found.');

        return response()->json([
            'status' => true,
            'data' => $record,
        ]);
    }

    public function destroy(string $resource, int|string $id): JsonResponse
    {
        $schema = $this->resources->find($resource);

        abort_if($schema === null, 404, 'WordVel resource not found.');

        abort_if(! $this->repository->delete($schema, $id), 404, 'WordVel resource record not found.');

        return response()->json([
            'status' => true,
            'data' => null,
        ]);
    }

    public function options(Request $request, string $resource): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => $this->repository->options(
                $resource,
                $request->string('search')->toString(),
                $request->input('ids', []),
                LocalizedValue::languageFromRequest($request),
            ),
        ]);
    }

    public function createRelatedFromMedia(Request $request, string $resource, int|string $id, string $field): JsonResponse
    {
        $schema = $this->resources->find($resource);

        abort_if($schema === null, 404, 'WordVel resource not found.');

        $record = $this->repository->createRelatedFromMedia(
            $schema,
            $id,
            $field,
            $request->input('media', []),
            LocalizedValue::languageFromRequest($request),
        );

        abort_if($record === null, 404, 'WordVel resource record not found.');

        return response()->json([
            'status' => true,
            'data' => $record,
        ]);
    }

    public function createRelated(Request $request, string $resource, int|string $id, string $field): JsonResponse
    {
        $schema = $this->resources->find($resource);

        abort_if($schema === null, 404, 'WordVel resource not found.');

        $record = $this->repository->createRelated(
            $schema,
            $id,
            $field,
            $request->input('values', []),
            LocalizedValue::languageFromRequest($request),
        );

        abort_if($record === null, 404, 'WordVel resource record not found.');

        return response()->json([
            'status' => true,
            'data' => $record,
        ], 201);
    }
}
