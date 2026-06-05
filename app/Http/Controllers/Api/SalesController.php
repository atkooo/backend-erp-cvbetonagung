<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SalesRequest;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;

class SalesController extends Controller
{
    /**
     * @var array<string, array{model: class-string<Model>, searchable: array<int, string>, sortable: array<int, string>, relations?: array<int, string>}>
     */
    private const RESOURCES = [
        'quotations' => [
            'model' => Quotation::class,
            'searchable' => ['quotation_number', 'notes'],
            'sortable' => ['quotation_number', 'quotation_date', 'valid_until', 'status', 'total', 'created_at'],
            'relations' => ['customer', 'createdBy', 'items.product'],
        ],
        'quotation-items' => [
            'model' => QuotationItem::class,
            'searchable' => ['description'],
            'sortable' => ['quantity', 'unit_price', 'subtotal'],
            'relations' => ['quotation', 'product'],
        ],
        'sales-orders' => [
            'model' => SalesOrder::class,
            'searchable' => ['order_number', 'notes'],
            'sortable' => ['order_number', 'order_date', 'status', 'total', 'created_at'],
            'relations' => ['quotation', 'customer', 'items.product'],
        ],
        'sales-order-items' => [
            'model' => SalesOrderItem::class,
            'searchable' => ['description'],
            'sortable' => ['quantity', 'unit_price', 'subtotal'],
            'relations' => ['salesOrder', 'product'],
        ],
        'delivery-orders' => [
            'model' => DeliveryOrder::class,
            'searchable' => ['delivery_number', 'receiver_name', 'notes'],
            'sortable' => ['delivery_number', 'delivery_date', 'received_at', 'status', 'created_at'],
            'relations' => ['salesOrder', 'customer', 'items.product', 'items.salesOrderItem'],
        ],
        'delivery-order-items' => [
            'model' => DeliveryOrderItem::class,
            'searchable' => [],
            'sortable' => ['quantity'],
            'relations' => ['deliveryOrder', 'salesOrderItem', 'product'],
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

    public function store(SalesRequest $request, string $resource): JsonResponse
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

    public function update(SalesRequest $request, string $resource, string $id): JsonResponse
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
        foreach ([
            'customer_id',
            'quotation_id',
            'sales_order_id',
            'delivery_order_id',
            'product_id',
            'status',
        ] as $column) {
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
        abort_unless(array_key_exists($resource, self::RESOURCES), 404, 'Unknown sales resource.');

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
