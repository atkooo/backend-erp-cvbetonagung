<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goods_receipt_notes', function (Blueprint $table) {
            if (! Schema::hasColumn('goods_receipt_notes', 'to_location_id')) {
                $table->foreignUuid('to_location_id')
                    ->nullable()
                    ->after('warehouse_id')
                    ->constrained('storage_locations')
                    ->nullOnDelete();
            }

            $table->foreignUuid('purchase_order_id')
                ->nullable()
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('goods_receipt_notes', function (Blueprint $table) {
            if (Schema::hasColumn('goods_receipt_notes', 'to_location_id')) {
                $table->dropConstrainedForeignId('to_location_id');
            }

            $table->foreignUuid('purchase_order_id')
                ->nullable(false)
                ->change();
        });
    }
};
