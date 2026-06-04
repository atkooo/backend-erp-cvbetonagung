<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'code',
    'customer_id',
    'quotation_id',
    'sales_order_id',
    'project_name',
    'location',
    'project_type',
    'project_spec',
    'contract_value',
    'deadline',
    'progress',
    'status',
])]
class Project extends Model
{
    use HasUuids;

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function timelines(): HasMany
    {
        return $this->hasMany(ProjectTimeline::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ProjectDocument::class);
    }

    public function budgetItems(): HasMany
    {
        return $this->hasMany(ProjectBudgetItem::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function termins(): HasMany
    {
        return $this->hasMany(ProjectTermin::class);
    }

    public function productionWorkOrders(): HasMany
    {
        return $this->hasMany(ProductionWorkOrder::class);
    }

    protected function casts(): array
    {
        return [
            'contract_value' => 'decimal:2',
            'deadline' => 'date',
            'progress' => 'integer',
        ];
    }
}
