<?php

namespace App\Models;

use App\Traits\GeneratesDocumentNumber;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['code', 'name', 'phone', 'email', 'city', 'address', 'status'])]
class Customer extends Model
{
    use HasFactory, GeneratesDocumentNumber, HasUuids, SoftDeletes;

    public function documentNumberPrefix(): string
    {
        return 'CUST';
    }

    public function documentNumberField(): string
    {
        return 'code';
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class);
    }

    public function salesOrders(): HasMany
    {
        return $this->hasMany(SalesOrder::class);
    }

    public function deliveryOrders(): HasMany
    {
        return $this->hasMany(DeliveryOrder::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function productReturns(): HasMany
    {
        return $this->hasMany(ProductReturn::class);
    }
}
