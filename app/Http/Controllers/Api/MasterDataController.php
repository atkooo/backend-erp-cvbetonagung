<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\MasterDataRequest;
use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;

class MasterDataController extends Controller
{
    /**
     * @var array<string, array{model: class-string<Model>, table: string, searchable: array<int, string>, sortable: array<int, string>, relations?: array<int, string>}>
     */
    private const RESOURCES = [
        'customers' => [
            'model' => Customer::class,
            'table' => 'customers',
            'searchable' => ['code', 'name', 'phone', 'email', 'city'],
            'sortable' => ['code', 'name', 'city', 'status', 'created_at'],
        ],
        'suppliers' => [
            'model' => Supplier::class,
            'table' => 'suppliers',
            'searchable' => ['code', 'name', 'contact_name', 'phone', 'city'],
            'sortable' => ['code', 'name', 'city', 'status', 'created_at'],
        ],
        'product-categories' => [
            'model' => ProductCategory::class,
            'table' => 'product_categories',
            'searchable' => ['name', 'description'],
            'sortable' => ['name', 'status', 'created_at'],
        ],
        'units' => [
            'model' => Unit::class,
            'table' => 'units',
            'searchable' => ['code', 'name'],
            'sortable' => ['code', 'name', 'created_at'],
        ],
        'warehouses' => [
            'model' => Warehouse::class,
            'table' => 'warehouses',
            'searchable' => ['code', 'name', 'type', 'address'],
            'sortable' => ['code', 'name', 'type', 'status', 'created_at'],
        ],
        'storage-locations' => [
            'model' => StorageLocation::class,
            'table' => 'storage_locations',
            'searchable' => ['code', 'name', 'description'],
            'sortable' => ['code', 'name', 'created_at'],
            'relations' => ['warehouse'],
        ],
        'products' => [
            'model' => Product::class,
            'table' => 'products',
            'searchable' => ['sku', 'name', 'qr_value'],
            'sortable' => ['sku', 'name', 'stock_status', 'status', 'created_at'],
            'relations' => ['category', 'unit'],
        ],
        'company-settings' => [
            'model' => CompanySetting::class,
            'table' => 'company_settings',
            'searchable' => ['company_name', 'operational_email', 'contact_phone'],
            'sortable' => ['company_name', 'updated_at'],
            'relations' => ['updatedBy'],
        ],
    ];

    public function index(Request $request, string $resource): JsonResponse
    {
        $config = $this->resourceConfig($resource);
        $perPage = max(1, min((int) $request->integer('per_page', 15), 100));
        $sort = $this->sortColumn($request, $config);
        $direction = $request->string('direction')->lower()->value() === 'desc' ? 'desc' : 'asc';

        $query = $this->query($config);
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

    public function store(MasterDataRequest $request, string $resource): JsonResponse
    {
        $config = $this->resourceConfig($resource);
        $modelClass = $config['model'];
        $model = $modelClass::query()->create($request->validated());

        return response()->json(['data' => $model->fresh($config['relations'] ?? [])], 201);
    }

    public function show(string $resource, string $id): JsonResponse
    {
        $config = $this->resourceConfig($resource);

        return response()->json(['data' => $this->findModel($config, $id)]);
    }

    public function update(MasterDataRequest $request, string $resource, string $id): JsonResponse
    {
        $config = $this->resourceConfig($resource);
        $model = $this->findModel($config, $id);

        $model->fill($request->validated());
        $model->save();

        return response()->json(['data' => $model->fresh($config['relations'] ?? [])]);
    }

    public function destroy(string $resource, string $id): JsonResponse|Response
    {
        $config = $this->resourceConfig($resource);
        $model = $this->findModel($config, $id);

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
     * @param array<string, mixed> $config
     */
    private function query(array $config): Builder
    {
        /** @var class-string<Model> $modelClass */
        $modelClass = $config['model'];

        return $modelClass::query()->with($config['relations'] ?? []);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function applyFilters(Builder $query, Request $request, array $config): void
    {
        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->value());
        }

        if (! $request->filled('q')) {
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
     * @param array<string, mixed> $config
     */
    private function findModel(array $config, string $id): Model
    {
        return $this->query($config)->whereKey($id)->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function resourceConfig(string $resource): array
    {
        abort_unless(array_key_exists($resource, self::RESOURCES), 404, 'Unknown master data resource.');

        return self::RESOURCES[$resource];
    }

    /**
     * @param array<string, mixed> $config
     */
    private function sortColumn(Request $request, array $config): string
    {
        $sort = $request->string('sort', Arr::first($config['sortable']))->value();

        return in_array($sort, $config['sortable'], true)
            ? $sort
            : Arr::first($config['sortable']);
    }
}
