<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotation_items', function (Blueprint $table) {
            $table->decimal('piece_count', 18, 2)->nullable()->after('description');
            $table->decimal('length', 18, 2)->nullable()->after('piece_count');
            $table->string('specification')->nullable()->after('length');
        });

        Schema::table('sales_order_items', function (Blueprint $table) {
            $table->decimal('piece_count', 18, 2)->nullable()->after('description');
            $table->decimal('length', 18, 2)->nullable()->after('piece_count');
            $table->string('specification')->nullable()->after('length');
        });
    }

    public function down(): void
    {
        Schema::table('sales_order_items', function (Blueprint $table) {
            $table->dropColumn(['piece_count', 'length', 'specification']);
        });

        Schema::table('quotation_items', function (Blueprint $table) {
            $table->dropColumn(['piece_count', 'length', 'specification']);
        });
    }
};
