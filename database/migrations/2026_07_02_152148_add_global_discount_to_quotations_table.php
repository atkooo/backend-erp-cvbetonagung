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
        Schema::table('quotations', function (Blueprint $table) {
            $table->enum('global_discount_type', ['percentage', 'nominal'])->nullable()->after('status');
            $table->decimal('global_discount_value', 15, 2)->nullable()->after('global_discount_type');
            $table->decimal('global_discount_amount', 15, 2)->default(0)->after('global_discount_value');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropColumn(['global_discount_type', 'global_discount_value', 'global_discount_amount']);
        });
    }
};
