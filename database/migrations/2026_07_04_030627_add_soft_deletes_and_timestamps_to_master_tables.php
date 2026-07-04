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
        Schema::table('units', function (Blueprint $table) {
            $table->timestamp('updated_at')->nullable()->after('created_at');
        });

        $tables = [
            'customers',
            'suppliers',
            'product_categories',
            'units',
            'warehouses',
            'storage_locations',
            'products',
        ];

        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = [
            'customers',
            'suppliers',
            'product_categories',
            'units',
            'warehouses',
            'storage_locations',
            'products',
        ];

        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }

        Schema::table('units', function (Blueprint $table) {
            $table->dropColumn('updated_at');
        });
    }
};
