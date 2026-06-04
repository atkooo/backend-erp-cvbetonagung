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
        Schema::create('approval_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('approval_number')->unique();
            $table->string('request_type');
            $table->foreignUuid('requester_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reference_type')->nullable();
            $table->uuid('reference_id')->nullable();
            $table->string('reference_number')->nullable();
            $table->text('change_summary')->nullable();
            $table->decimal('amount', 18, 2)->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_notes')->nullable();
        });

        Schema::create('product_stocks', function (Blueprint $table) {
            $table->foreignUuid('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignUuid('location_id')->constrained('storage_locations')->restrictOnDelete();
            $table->decimal('quantity', 18, 2)->default(0);
            $table->timestamp('updated_at')->nullable();

            $table->primary(['product_id', 'location_id']);
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignUuid('from_location_id')->nullable()->constrained('storage_locations')->nullOnDelete();
            $table->foreignUuid('to_location_id')->nullable()->constrained('storage_locations')->nullOnDelete();
            $table->string('type');
            $table->decimal('quantity', 18, 2);
            $table->string('reference_type')->nullable();
            $table->uuid('reference_id')->nullable();
            $table->string('reference_number')->nullable();
            $table->foreignUuid('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('movement_at');
            $table->timestamp('created_at')->nullable();

            $table->index(['reference_type', 'reference_id']);
            $table->index('movement_at');
        });

        Schema::create('stock_opname_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('opname_number')->unique();
            $table->foreignUuid('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignUuid('started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->text('notes')->nullable();
        });

        Schema::create('stock_opname_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('session_id')->constrained('stock_opname_sessions')->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignUuid('location_id')->constrained('storage_locations')->restrictOnDelete();
            $table->decimal('system_qty', 18, 2);
            $table->decimal('physical_qty', 18, 2);
            $table->decimal('difference_qty', 18, 2);
            $table->text('notes')->nullable();
            $table->foreignUuid('approval_request_id')->nullable()->constrained('approval_requests')->nullOnDelete();

            $table->unique(['session_id', 'product_id', 'location_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_opname_items');
        Schema::dropIfExists('stock_opname_sessions');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('product_stocks');
        Schema::dropIfExists('approval_requests');
    }
};
