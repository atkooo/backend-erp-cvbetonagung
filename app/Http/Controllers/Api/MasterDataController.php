<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\MasterDataRequest;
use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\Discount;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MasterDataController extends ApiResourceController
{
    /**
     * @var array<string, array{model: class-string<Model>, searchable: array<int, string>, sortable: array<int, string>, relations?: array<int, string>}>
     */
    
    private const RESOURCES = [
        'customers' => [
            'model' => Customer::class,
            'searchable' => ['code', 'name', 'phone', 'email', 'city'],
            'sortable' => ['code', 'name', 'city', 'status', 'created_at'],
        ],
        'suppliers' => [
            'model' => Supplier::class,
            'searchable' => ['code', 'name', 'contact_name', 'phone', 'city'],
            'sortable' => ['code', 'name', 'city', 'status', 'created_at'],
        ],
        'product-categories' => [
            'model' => ProductCategory::class,
            'searchable' => ['name', 'description'],
            'sortable' => ['name', 'status', 'created_at'],
        ],
        'units' => [
            'model' => Unit::class,
            'searchable' => ['code', 'name'],
            'sortable' => ['code', 'name', 'created_at'],
        ],
        'warehouses' => [
            'model' => Warehouse::class,
            'searchable' => ['code', 'name', 'type', 'address'],
            'sortable' => ['code', 'name', 'type', 'status', 'created_at'],
        ],
        'storage-locations' => [
            'model' => StorageLocation::class,
            'searchable' => ['code', 'name', 'description'],
            'sortable' => ['code', 'name', 'created_at'],
            'relations' => ['warehouse'],
        ],
        'products' => [
            'model' => Product::class,
            'searchable' => ['sku', 'name', 'qr_value'],
            'sortable' => ['sku', 'name', 'stock_status', 'status', 'created_at'],
            'relations' => ['category', 'unit', 'discount'],
        ],
        'company-settings' => [
            'model' => CompanySetting::class,
            'searchable' => ['company_name', 'operational_email', 'contact_phone'],
            'sortable' => ['company_name', 'updated_at'],
            'relations' => ['updatedBy'],
        ],
        'discounts' => [
            'model' => Discount::class,
            'searchable' => ['name', 'type'],
            'sortable' => ['name', 'type', 'is_active', 'created_at'],
        ],
    ];

    /**
     * @return array<string, array{model: class-string<Model>, searchable: array<int, string>, sortable: array<int, string>, relations?: array<int, string>}>
     */
    protected function resources(): array
    {
        return self::RESOURCES;
    }

    public function index(Request $request, string $resource): JsonResponse
    {
        return $this->indexResource($request, $resource);
    }

    public function store(MasterDataRequest $request, string $resource): JsonResponse
    {
        return $this->storeResource($resource, $request->validated());
    }

    public function show(string $resource, string $id): JsonResponse
    {
        return $this->showResource($resource, $id);
    }

    public function update(MasterDataRequest $request, string $resource, string $id): JsonResponse
    {
        return $this->updateResource($resource, $id, $request->validated());
    }

    public function destroy(string $resource, string $id): JsonResponse|Response
    {
        return $this->destroyResource($resource, $id);
    }

    protected function filterableColumns(): array
    {
        return ['status'];
    }

    protected function resourceQuery(array $config): \Illuminate\Database\Eloquent\Builder
    {
        $query = parent::resourceQuery($config);

        if ($config['model'] === Product::class) {
            $query->withSum(['salesOrderItems as booked_stock' => function ($query) {
                $query->whereHas('salesOrder', function ($q) {
                    $q->whereIn('status', ['approved', 'processing', 'pending_delivery']);
                });
            }], 'quantity');
        }

        return $query;
    }
}
