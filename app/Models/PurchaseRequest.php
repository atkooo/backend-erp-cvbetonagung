<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseRequest extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'pr_number',
        'requester_id',
        'request_date',
        'required_date',
        'department',
        'status',
        'notes',
    ];

    protected $casts = [
        'request_date' => 'date',
        'required_date' => 'date',
    ];

    public function requester()
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function items()
    {
        return $this->hasMany(PurchaseRequestItem::class);
    }

    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class);
    }
}
