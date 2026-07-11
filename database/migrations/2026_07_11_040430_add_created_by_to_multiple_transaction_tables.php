<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The tables to add the created_by column to.
     */
    protected array $tables = [
        'sales_orders',
        'purchase_orders',
        'invoices',
        'delivery_orders',
        'goods_receipt_notes',
        'purchase_requests',
        'rfqs',
        'supplier_payables',
        'payments',
        'cash_transactions',
        'production_work_orders',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            if (Schema::hasTable($tableName) && ! Schema::hasColumn($tableName, 'created_by')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('multiple_transaction_tables', function (Blueprint $table) {
            //
        });
    }
};
