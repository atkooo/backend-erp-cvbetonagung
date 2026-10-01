<?php

namespace App\Services;

use App\Models\ProductionWorkOrder;
use App\Models\StockProductionRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class StockProductionRequestService
{
    /**
     * Buat Permintaan Produksi Stok (SPR) baru beserta items-nya.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function createRequest(array $attributes, ?string $userId = null): StockProductionRequest
    {
        return DB::transaction(function () use ($attributes, $userId): StockProductionRequest {
            $spr = StockProductionRequest::query()->create([
                'storage_location_id' => $attributes['storage_location_id'] ?? null,
                'requested_by' => $userId ?? $attributes['requested_by'] ?? null,
                'request_date' => $attributes['request_date'] ?? date('Y-m-d'),
                'due_date' => $attributes['due_date'] ?? Carbon::now()->addDays(7)->toDateString(),
                'status' => 'draft',
                'notes' => $attributes['notes'] ?? null,
                'created_by' => $userId,
            ]);

            if (isset($attributes['items']) && is_array($attributes['items'])) {
                foreach ($attributes['items'] as $item) {
                    $spr->items()->create([
                        'product_id' => $item['product_id'],
                        'target_qty' => $item['target_qty'],
                        'completed_qty' => 0,
                        'notes' => $item['notes'] ?? null,
                    ]);
                }
            }

            return $spr->fresh(['items.product', 'storageLocation', 'requestedBy']);
        });
    }

    /**
     * Setujui (Approve) Permintaan Produksi Stok dan otomatis terbitkan Work Order (WO).
     *
     * @return array{request: StockProductionRequest, work_orders: array<int, ProductionWorkOrder>}
     */
    public function approveRequest(string $id, ?string $userId = null): array
    {
        return DB::transaction(function () use ($id, $userId): array {
            /** @var StockProductionRequest $spr */
            $spr = StockProductionRequest::query()
                ->with(['items.product'])
                ->lockForUpdate()
                ->whereKey($id)
                ->firstOrFail();

            abort_if($spr->isCancelled(), 422, 'Permintaan Produksi Stok yang sudah dibatalkan tidak dapat disetujui.');
            abort_if($spr->status !== 'draft', 422, 'Hanya Permintaan Produksi Stok berstatus Draft yang dapat disetujui.');
            abort_if($spr->items->isEmpty(), 422, 'Permintaan Produksi Stok harus memiliki minimal satu item.');

            $spr->forceFill([
                'status' => 'approved',
                'approved_by' => $userId,
            ])->save();

            $createdWos = [];

            foreach ($spr->items as $item) {
                $product = $item->product;
                if (! $product) {
                    continue;
                }

                // Cek idempotensi jika WO untuk SPR dan produk ini sudah pernah dibuat
                $existingWo = ProductionWorkOrder::query()
                    ->where('stock_production_request_id', $spr->id)
                    ->where('product_id', $product->id)
                    ->first();

                if ($existingWo !== null) {
                    $createdWos[] = $existingWo;

                    continue;
                }

                $dueDate = $spr->due_date
                    ? Carbon::parse($spr->due_date)->toDateString()
                    : Carbon::now()->addDays(7)->toDateString();

                $wo = ProductionWorkOrder::query()->create([
                    'product_id' => $product->id,
                    'stock_production_request_id' => $spr->id,
                    'source_label' => "SPR: {$spr->request_number} - Restok Gudang",
                    'stage' => 'Draft',
                    'target_qty' => (float) $item->target_qty,
                    'completed_qty' => 0,
                    'progress' => 0,
                    'due_date' => $dueDate,
                ]);

                $createdWos[] = $wo;
            }

            return [
                'request' => $spr->fresh(['items.product', 'workOrders']),
                'work_orders' => $createdWos,
            ];
        });
    }
}
