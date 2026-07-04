<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;

abstract class ApiResourceController extends Controller
{
    /**
     * @return array<string, array{model: class-string<Model>, searchable: array<int, string>, sortable: array<int, string>, relations?: array<int, string>}>
     */
    abstract protected function resources(): array;

    protected function indexResource(Request $request, string $resource): JsonResponse
    {
        $config = $this->resourceConfig($resource);
        $perPage = max(1, min((int) $request->integer('per_page', 15), 100));
        [$sort, $direction] = $this->sortClause($request, $config);

        $query = $this->resourceQuery($config);
        $this->applyFilters($query, $request, $config);

        $paginator = $query
            ->orderBy($sort, $direction)
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function storeResource(string $resource, array $attributes): JsonResponse
    {
        $config = $this->resourceConfig($resource);
        $modelClass = $config['model'];
        $model = $modelClass::query()->create($attributes);

        return response()->json(['data' => $model->fresh($config['relations'] ?? [])], 201);
    }

    protected function showResource(string $resource, string $id): JsonResponse
    {
        $config = $this->resourceConfig($resource);

        return response()->json(['data' => $this->findResourceModel($config, $id)]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function updateResource(string $resource, string $id, array $attributes): JsonResponse
    {
        $config = $this->resourceConfig($resource);
        $model = $this->findResourceModel($config, $id);

        $model->fill($attributes);
        $model->save();

        return response()->json(['data' => $model->fresh($config['relations'] ?? [])]);
    }

    protected function destroyResource(string $resource, string $id): JsonResponse|Response
    {
        $config = $this->resourceConfig($resource);
        $model = $this->findResourceModel($config, $id);

        try {
            $model->delete();
        } catch (QueryException) {
            return response()->json([
                'message' => 'Record cannot be deleted because it is referenced by other ERP data.',
            ], 409);
        }

        return response()->noContent();
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function resourceQuery(array $config): Builder
    {
        /** @var class-string<Model> $modelClass */
        $modelClass = $config['model'];

        return $modelClass::query()->with($config['relations'] ?? []);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function applyFilters(Builder $query, Request $request, array $config): void
    {
        foreach ($this->filterableColumns() as $column) {
            if ($request->filled($column)) {
                $query->where($column, $request->string($column)->value());
            }
        }

        if (! $request->filled('q') || $config['searchable'] === []) {
            return;
        }

        $search = $request->string('q')->value();
        $query->where(function (Builder $query) use ($config, $search): void {
            foreach ($config['searchable'] as $column) {
                $query->orWhere($column, 'like', '%'.$search.'%');
            }
        });
    }

    /**
     * @return array<int, string>
     */
    protected function filterableColumns(): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function findResourceModel(array $config, string $id): Model
    {
        return $this->resourceQuery($config)->whereKey($id)->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    protected function resourceConfig(string $resource): array
    {
        $resources = $this->resources();

        abort_unless(array_key_exists($resource, $resources), 404, 'Unknown API resource.');

        return $resources[$resource];
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array{0: string, 1: string}
     */
    protected function sortClause(Request $request, array $config): array
    {
        $requestedSort = $request->string('sort', Arr::first($config['sortable']))->value();
        $direction = $request->string('direction')->lower()->value() === 'desc' ? 'desc' : 'asc';

        if (str_starts_with($requestedSort, '-')) {
            $requestedSort = substr($requestedSort, 1);
            $direction = 'desc';
        }

        $sort = in_array($requestedSort, $config['sortable'], true)
            ? $requestedSort
            : Arr::first($config['sortable']);

        return [$sort, $direction];
    }
}
