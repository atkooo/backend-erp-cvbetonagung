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
        if (!Schema::hasTable('discounts')) {
            Schema::create('discounts', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->enum('type', ['percentage', 'nominal']);
                $table->decimal('value', 18, 2);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'discount_type')) {
                $table->dropColumn('discount_type');
            }
            if (Schema::hasColumn('products', 'discount_value')) {
                $table->dropColumn('discount_value');
            }
            if (!Schema::hasColumn('products', 'discount_id')) {
                $table->foreignUuid('discount_id')->nullable()->constrained('discounts')->nullOnDelete()->after('selling_price');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['discount_id']);
            $table->dropColumn('discount_id');
            $table->enum('discount_type', ['percentage', 'nominal'])->nullable()->after('selling_price');
            $table->decimal('discount_value', 18, 2)->nullable()->after('discount_type');
        });

        Schema::dropIfExists('discounts');
    }
};
