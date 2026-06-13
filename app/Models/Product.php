<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use App\Traits\GeneratesDocumentNumber;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'category_id',
    'unit_id',
    'sku',
    'type',
    'name',
    'length',
    'motif',
    'cost_price',
    'selling_price',
    'min_stock',
    'stock_status',
    'qr_value',
    'status',
])]
class Product extends Model
{
    use HasUuids, GeneratesDocumentNumber;

    public function documentNumberPrefix(): string
    {
        return 'PRD';
    }

    public function documentNumberField(): string
    {
        return 'sku';
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(ProductStock::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function stockOpnameItems(): HasMany
    {
        return $this->hasMany(StockOpnameItem::class);
    }

    public function quotationItems(): HasMany
    {
        return $this->hasMany(QuotationItem::class);
    }

    public function salesOrderItems(): HasMany
    {
        return $this->hasMany(SalesOrderItem::class);
    }

    public function deliveryOrderItems(): HasMany
    {
        return $this->hasMany(DeliveryOrderItem::class);
    }

    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function purchaseOrderItems(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function returnItems(): HasMany
    {
        return $this->hasMany(ReturnItem::class);
    }

    public function productionWorkOrders(): HasMany
    {
        return $this->hasMany(ProductionWorkOrder::class);
    }

    public function productionWorkOrderItems(): HasMany
    {
        return $this->hasMany(ProductionWorkOrderItem::class);
    }

    public function boms(): HasMany
    {
        return $this->hasMany(Bom::class);
    }

    public function bomItemsAsComponent(): HasMany
    {
        return $this->hasMany(BomItem::class, 'component_product_id');
    }

    protected function casts(): array
    {
        return [
            'cost_price' => 'decimal:2',
            'selling_price' => 'decimal:2',
            'min_stock' => 'decimal:2',
        ];
    }
}
