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
        if (Schema::hasColumn('sales_order_items', 'discount_id')) {
            try {
                Schema::table('sales_order_items', function (Blueprint $table) {
                    $table->dropForeign(['discount_id']);
                });
            } catch (Exception $e) {
                // Abaikan jika foreign key sudah tidak ada
            }

            Schema::table('sales_order_items', function (Blueprint $table) {
                $table->dropColumn('discount_id');
            });
        }

        if (! Schema::hasColumn('sales_order_items', 'discount_amount')) {
            Schema::table('sales_order_items', function (Blueprint $table) {
                $table->decimal('discount_amount', 18, 2)->default(0)->after('unit_price');
            });
        }

        Schema::dropIfExists('discounts');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('discounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->enum('type', ['percentage', 'nominal']);
            $table->decimal('value', 18, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('sales_order_items', function (Blueprint $table) {
            $table->foreignUuid('discount_id')->nullable()->constrained('discounts')->nullOnDelete()->after('quantity');
        });
    }
};
