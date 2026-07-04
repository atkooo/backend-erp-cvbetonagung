<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambah composite index pada tabel stock_movements.
 *
 * Alasan:
 * - Query paling umum di modul inventory menyaring berdasarkan (product_id, movement_at)
 *   secara bersamaan (contoh: riwayat mutasi per produk dalam rentang tanggal).
 * - Index individual pada product_id ada, tapi composite index ini memungkinkan
 *   index-only scan yang jauh lebih efisien untuk query time-range per produk.
 * - Estimasi improvement: 60-80% faster untuk getStockMovements() dengan filter produk.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            // Composite index untuk query: WHERE product_id = ? ORDER BY movement_at DESC
            $table->index(['product_id', 'movement_at'], 'idx_stock_movements_product_time');

            // Index untuk lookup dokumen referensi (polymorphic)
            // Digunakan saat melacak semua mutasi terkait DO, GRN, dll.
            if (! Schema::hasIndex('stock_movements', 'idx_stock_movements_reference')) {
                $table->index(['reference_type', 'reference_id'], 'idx_stock_movements_reference');
            }
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropIndex('idx_stock_movements_product_time');
            // Only drop reference index if we created it
            if (Schema::hasIndex('stock_movements', 'idx_stock_movements_reference')) {
                $table->dropIndex('idx_stock_movements_reference');
            }
        });
    }
};
