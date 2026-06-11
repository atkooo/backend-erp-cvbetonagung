<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

use App\Traits\GeneratesDocumentNumber;

#[Fillable(['code', 'name', 'contact_name', 'phone', 'city', 'address', 'status'])]
class Supplier extends Model
{
    use HasUuids, GeneratesDocumentNumber;

    public function documentNumberPrefix(): string
    {
        return 'SPL';
    }

    public function documentNumberField(): string
    {
        return 'code';
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function supplierPayables(): HasMany
    {
        return $this->hasMany(SupplierPayable::class);
    }

    public function productReturns(): HasMany
    {
        return $this->hasMany(ProductReturn::class);
    }
}
