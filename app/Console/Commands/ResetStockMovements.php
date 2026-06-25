<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\ProductStock;
use App\Models\StockMovement;
use Carbon\Carbon;

class ResetStockMovements extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'inventory:reset-movements';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Kosongkan tabel stock_movements dan buat Saldo Awal berdasarkan product_stocks saat ini';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if (!$this->confirm('Apakah Anda yakin ingin menghapus semua data mutasi stok dan meresetnya menjadi Saldo Awal?')) {
            $this->info('Operasi dibatalkan.');
            return;
        }

        $this->info('Memulai reset mutasi stok...');

        DB::beginTransaction();

        try {
            // 1. Truncate tabel stock_movements
            $this->info('Mengosongkan tabel stock_movements...');
            // Nonaktifkan foreign key checks untuk MySQL/MariaDB saat truncate
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            StockMovement::truncate();
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');

            // 2. Ambil semua stok produk yang bernilai > 0
            $stocks = ProductStock::where('quantity', '>', 0)->get();
            $this->info('Ditemukan ' . $stocks->count() . ' record stok produk (quantity > 0).');

            $movements = [];
            $now = Carbon::now();

            foreach ($stocks as $stock) {
                $movements[] = [
                    'id' => Str::uuid()->toString(),
                    'product_id' => $stock->product_id,
                    'from_location_id' => null,
                    'to_location_id' => $stock->location_id,
                    'type' => 'in', // Tipe masuk untuk saldo awal
                    'quantity' => $stock->quantity,
                    'reference_type' => null,
                    'reference_id' => null,
                    'reference_number' => 'SALDO-AWAL',
                    'handled_by' => null, 
                    'notes' => 'Saldo Awal Sistem',
                    'movement_at' => $now,
                ];
            }

            // 3. Insert dalam bentuk chunk untuk efisiensi
            $this->info('Memasukkan data Saldo Awal...');
            $chunks = array_chunk($movements, 500);
            foreach ($chunks as $chunk) {
                StockMovement::insert($chunk);
            }

            DB::commit();

            $this->info('Berhasil mereset riwayat mutasi dan membuat Saldo Awal baru!');
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('Terjadi kesalahan: ' . $e->getMessage());
        }
    }
}
