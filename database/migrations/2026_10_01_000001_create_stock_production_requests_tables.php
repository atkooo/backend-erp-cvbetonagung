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
        Schema::create('stock_production_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('request_number')->unique();
            $table->foreignUuid('storage_location_id')->nullable()->constrained('storage_locations')->nullOnDelete();
            $table->foreignUuid('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('request_date');
            $table->date('due_date')->nullable();
            $table->string('status')->default('draft'); // draft, approved, in_progress, completed, cancelled
            $table->text('notes')->nullable();
            $table->foreignUuid('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('stock_production_request_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('stock_production_request_id')->constrained('stock_production_requests')->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('target_qty', 18, 2);
            $table->decimal('completed_qty', 18, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::table('production_work_orders', function (Blueprint $table) {
            $table->foreignUuid('stock_production_request_id')
                ->nullable()
                ->after('project_id')
                ->constrained('stock_production_requests')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('production_work_orders', function (Blueprint $table) {
            $table->dropForeign(['stock_production_request_id']);
            $table->dropColumn('stock_production_request_id');
        });

        Schema::dropIfExists('stock_production_request_items');
        Schema::dropIfExists('stock_production_requests');
    }
};
