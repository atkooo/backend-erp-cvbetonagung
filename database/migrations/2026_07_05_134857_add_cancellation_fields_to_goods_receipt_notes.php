<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambahkan kolom cancelled_by, cancelled_at, cancel_reason ke goods_receipt_notes
 * agar GRN bisa ikut dalam hierarki pembatalan (Cancellable trait).
 * Juga menambahkan kolom status jika belum ada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goods_receipt_notes', function (Blueprint $table) {
            if (! Schema::hasColumn('goods_receipt_notes', 'status')) {
                $table->string('status')->default('received')->after('notes');
            }

            if (! Schema::hasColumn('goods_receipt_notes', 'cancelled_by')) {
                $table->foreignUuid('cancelled_by')
                    ->nullable()
                    ->after('status')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('goods_receipt_notes', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('cancelled_by');
            }

            if (! Schema::hasColumn('goods_receipt_notes', 'cancel_reason')) {
                $table->text('cancel_reason')->nullable()->after('cancelled_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('goods_receipt_notes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn(['cancelled_at', 'cancel_reason']);
        });
    }
};
