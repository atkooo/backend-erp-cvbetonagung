<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = [
        'quotations',
        'sales_orders',
        'delivery_orders',
        'invoices',
        'purchase_orders',
        'payments',
        'supplier_payables',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->foreignUuid('cancelled_by')
                    ->nullable()
                    ->after('status')
                    ->constrained('users')
                    ->nullOnDelete();
                $table->timestamp('cancelled_at')->nullable()->after('cancelled_by');
                $table->text('cancel_reason')->nullable()->after('cancelled_at');
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropForeign([$blueprint->getTable().'_cancelled_by_foreign']);
                $blueprint->dropColumn(['cancelled_by', 'cancelled_at', 'cancel_reason']);
            });
        }
    }
};
