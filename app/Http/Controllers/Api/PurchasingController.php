<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PurchasingRequest;
use App\Models\ProductReturn;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\ReturnItem;
use App\Models\SupplierPayable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;

class PurchasingController extends Controller
{
    /**
     * @var array<string, array{model: class-string<Model>, searchable: array<int, string>, sortable: array<int, string>, relations?: array<int, string>}>
     */
    private const RESOURCES = [
        'purchase-orders' => [
            'model' => PurchaseOrder::class,
            'searchable' => ['po_number', 'notes'],
            'sortable' => ['po_number', 'po_date', 'status', 'total', 'created_at'],
            'relations' => ['supplier', 'items.product', 'supplierPayables'],
        ],
        'purchase-order-items' => [
            'model' => PurchaseOrderItem::class,
            'searchable' => ['description'],
            'sortable' => ['quantity', 'unit_price', 'received_qty', 'subtotal'],
            'relations' => ['purchaseOrder', 'product'],
        ],
        'supplier-payables' => [
            'model' => SupplierPayable::class,
            'searchable' => ['payable_number'],
            'sortable' => ['payable_number', 'due_date', 'amount', 'paid_amount', 'status', 'created_at'],
            'relations' => ['purchaseOrder', 'supplier'],
        ],
        'returns' => [
            'model' => ProductReturn::class,
            'searchable' => ['return_number', 'reason', 'qc_status'],
            'sortable' => ['return_number', 'type', 'qc_status', 'created_at'],
            'relations' => ['customer', 'supplier', 'salesOrder', 'purchaseOrder', 'createdBy', 'items.product'],
        ],
        'return-items' => [
            'model' => ReturnItem::class,
            'searchable' => ['notes'],
            'sortable' => ['quantity'],
            'relations' => ['productReturn', 'product'],
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

    public function store(PurchasingRequest $request, string $resource): JsonResponse
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

    public function update(PurchasingRequest $request, string $resource, string $id): JsonResponse
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
            'supplier_id',
            'purchase_order_id',
            'customer_id',
            'sales_order_id',
            'return_id',
            'product_id',
            'type',
            'status',
            'qc_status',
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
        abort_unless(array_key_exists($resource, self::RESOURCES), 404, 'Unknown purchasing resource.');

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
