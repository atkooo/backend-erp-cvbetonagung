<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use App\Traits\GeneratesDocumentNumber;

class Bag extends Model
{
    use HasUuids, GeneratesDocumentNumber;

    protected $fillable = [
        'bag_number',
        'date',
        'warehouse_id',
        'location_id',
        'type',
        'notes',
        'status',
        'created_by',
    ];

    public function documentNumberPrefix(): string
    {
        return 'BAG';
    }

    public function documentNumberField(): string
    {
        return 'bag_number';
    }

    public function items()
    {
        return $this->hasMany(BagItem::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function location()
    {
        return $this->belongsTo(StorageLocation::class, 'location_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
