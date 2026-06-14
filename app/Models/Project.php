<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use App\Traits\GeneratesDocumentNumber;
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
    use HasUuids, GeneratesDocumentNumber;

    protected static function booted(): void
    {
        static::created(function (Project $project) {
            $stages = [
                ['name' => 'Survey Lokasi', 'code_prefix' => 'TSK1'],
                ['name' => 'Produksi Workshop', 'code_prefix' => 'TSK2'],
                ['name' => 'Pengiriman Material', 'code_prefix' => 'TSK3'],
                ['name' => 'Pemasangan Scaffolding', 'code_prefix' => 'TSK4'],
                ['name' => 'Penyelesaian Pekerjaan', 'code_prefix' => 'TSK5'],
                ['name' => 'Selesai & Serah Terima', 'code_prefix' => 'TSK6'],
            ];

            $seq = 1;
            foreach ($stages as $stage) {
                $prjNum = str_replace('PRJ-', '', $project->code);
                $taskCode = $stage['code_prefix'] . '-' . $prjNum;
                
                ProjectTask::create([
                    'project_id' => $project->id,
                    'task_code' => $taskCode,
                    'task_name' => $stage['name'],
                    'status' => 'Pending',
                    'sequence' => $seq++,
                ]);
            }
        });
    }

    public function documentNumberPrefix(): string
    {
        return 'PRJ';
    }

    public function documentNumberField(): string
    {
        return 'code';
    }

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

    public function tasks(): HasMany
    {
        return $this->hasMany(ProjectTask::class)->orderBy('sequence');
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
