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
        Schema::create('production_work_order_tasks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('work_order_id')->constrained('production_work_orders')->cascadeOnDelete();
            $table->string('task_code')->unique();
            $table->string('task_name');
            $table->string('status')->default('Pending'); // Pending, In Progress, Completed
            $table->foreignUuid('assigned_to')->nullable()->constrained('employees')->nullOnDelete();
            $table->decimal('target_qty', 10, 2)->default(0);
            $table->decimal('completed_qty', 10, 2)->default(0);
            $table->decimal('reject_qty', 10, 2)->default(0);
            $table->integer('sequence')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_work_order_tasks');
    }
};
