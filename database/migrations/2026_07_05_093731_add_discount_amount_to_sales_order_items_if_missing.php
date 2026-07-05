<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Migration ini dibuat untuk memperbaiki kondisi di mana migration sebelumnya
     * (2026_07_02_144035_drop_discounts_table_and_update_sales_order_items) tercatat sudah
     * berjalan namun kolom discount_amount tidak benar-benar terbuat di database.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('sales_order_items', 'discount_amount')) {
            Schema::table('sales_order_items', function (Blueprint $table) {
                $table->decimal('discount_amount', 18, 2)->default(0)->after('unit_price');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('sales_order_items', 'discount_amount')) {
            Schema::table('sales_order_items', function (Blueprint $table) {
                $table->dropColumn('discount_amount');
            });
        }
    }
};
