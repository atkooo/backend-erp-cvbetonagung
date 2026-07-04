<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\GeneratesDocumentNumber;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'type', 'address', 'status'])]
class Warehouse SoftDeletes, extends Model
{
    use HasUuids, GeneratesDocumentNumber;

    public function documentNumberPrefix(): string
    {
        return 'WH';
    }

    public function documentNumberField(): string
    {
        return 'code';
    }

    public function storageLocations(): HasMany
    {
        return $this->hasMany(StorageLocation::class);
    }

    public function stockOpnameSessions(): HasMany
    {
        return $this->hasMany(StockOpnameSession::class);
    }
}
