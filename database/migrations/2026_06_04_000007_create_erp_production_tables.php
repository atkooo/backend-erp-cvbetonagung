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
        Schema::create('production_work_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('work_order_number')->unique();
            $table->foreignUuid('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignUuid('sales_order_id')->nullable()->constrained('sales_orders')->nullOnDelete();
            $table->foreignUuid('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->string('source_label')->nullable();
            $table->string('stage');
            $table->decimal('target_qty', 18, 2);
            $table->decimal('completed_qty', 18, 2)->default(0);
            $table->integer('progress')->default(0);
            $table->date('due_date')->nullable();
            $table->timestamps();
        });

        Schema::create('production_work_order_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('work_order_id')->constrained('production_work_orders')->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('quantity', 18, 2);
            $table->text('notes')->nullable();
        });

        Schema::create('production_work_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('work_order_id')->constrained('production_work_orders')->cascadeOnDelete();
            $table->foreignUuid('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->date('work_date');
            $table->string('stage');
            $table->decimal('made_qty', 18, 2)->default(0);
            $table->decimal('reject_qty', 18, 2)->default(0);
            $table->decimal('ok_qty', 18, 2)->default(0);
            $table->decimal('piece_rate', 18, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignUuid('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });

        Schema::create('boms', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('product_id')->constrained('products')->restrictOnDelete();
            $table->string('version');
            $table->date('effective_from')->nullable();
            $table->string('status');
            $table->decimal('total_cost', 18, 2)->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'version']);
        });

        Schema::create('bom_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('bom_id')->constrained('boms')->cascadeOnDelete();
            $table->foreignUuid('component_product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('component_name')->nullable();
            $table->decimal('quantity', 18, 2);
            $table->foreignUuid('unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->decimal('unit_cost', 18, 2);
            $table->decimal('subtotal', 18, 2);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bom_items');
        Schema::dropIfExists('boms');
        Schema::dropIfExists('production_work_logs');
        Schema::dropIfExists('production_work_order_items');
        Schema::dropIfExists('production_work_orders');
    }
};
