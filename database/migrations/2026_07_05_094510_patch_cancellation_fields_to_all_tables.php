<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Patch migration: memastikan kolom cancelled_by, cancelled_at, cancel_reason
 * benar-benar ada di semua tabel dokumen.
 *
 * Migration sebelumnya (2026_07_05_091219) sudah tercatat "Ran" di tabel migrations
 * namun beberapa kolom tidak benar-benar terbuat (kemungkinan ada error yang di-catch
 * diam-diam atau tabel dibuat ulang setelah migrasi itu berjalan).
 */
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
        foreach (self::TABLES as $tableName) {
            if (! Schema::hasColumn($tableName, 'cancelled_by')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->foreignUuid('cancelled_by')
                        ->nullable()
                        ->after('status')
                        ->constrained('users')
                        ->nullOnDelete();
                });
            }

            if (! Schema::hasColumn($tableName, 'cancelled_at')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->timestamp('cancelled_at')->nullable()->after('cancelled_by');
                });
            }

            if (! Schema::hasColumn($tableName, 'cancel_reason')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->text('cancel_reason')->nullable()->after('cancelled_at');
                });
            }
        }
    }

    public function down(): void
    {
        // Tidak di-rollback untuk mencegah kehilangan data audit pembatalan.
    }
};
