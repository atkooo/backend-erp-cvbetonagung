<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('delivery_orders', function (Blueprint $table) {
            $table->string('shipping_type')->default('free')->after('status');
            $table->decimal('shipping_cost', 18, 2)->default(0)->after('shipping_type');
            $table->string('shipping_payment_type')->default('company')->after('shipping_cost');
            $table->string('driver_name')->nullable()->after('shipping_payment_type');
            $table->string('vehicle_number')->nullable()->after('driver_name');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('shipping_cost', 18, 2)->default(0)->after('tax_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('delivery_orders', function (Blueprint $table) {
            $table->dropColumn([
                'shipping_type',
                'shipping_cost',
                'shipping_payment_type',
                'driver_name',
                'vehicle_number',
            ]);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['shipping_cost']);
        });
    }
};
