<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = [
        'purchase_requests',
        'rfqs',
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
        foreach (self::TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $blueprint) {
                if (Schema::hasColumn($tableName, 'cancelled_by')) {
                    $blueprint->dropForeign([$blueprint->getTable().'_cancelled_by_foreign']);
                }
                $blueprint->dropColumn(['cancelled_by', 'cancelled_at', 'cancel_reason']);
            });
        }
    }
};
