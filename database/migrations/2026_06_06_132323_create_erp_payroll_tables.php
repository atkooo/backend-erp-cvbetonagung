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
        Schema::create('salary_components', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('type'); // 'allowance', 'deduction'
            $table->boolean('is_taxable')->default(true);
            $table->boolean('is_fixed')->default(true); // fixed every month or variable (based on attendance)
            $table->decimal('default_amount', 18, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('employee_salaries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignUuid('salary_component_id')->constrained('salary_components')->cascadeOnDelete();
            $table->decimal('amount', 18, 2);
            $table->timestamps();

            $table->unique(['employee_id', 'salary_component_id']);
        });

        Schema::create('payrolls', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('employee_id')->constrained('employees')->restrictOnDelete();
            $table->string('payroll_number')->unique();
            $table->integer('period_month');
            $table->integer('period_year');
            $table->date('start_date');
            $table->date('end_date');
            $table->integer('total_attendance')->default(0);
            $table->integer('total_late_minutes')->default(0);
            $table->decimal('basic_salary', 18, 2)->default(0);
            $table->decimal('total_allowance', 18, 2)->default(0);
            $table->decimal('total_deduction', 18, 2)->default(0);
            $table->decimal('net_salary', 18, 2)->default(0);
            $table->string('status')->default('draft'); // draft, approved, paid
            $table->date('payment_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('payroll_details', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('payroll_id')->constrained('payrolls')->cascadeOnDelete();
            $table->foreignUuid('salary_component_id')->constrained('salary_components')->restrictOnDelete();
            $table->string('type'); // copy from component to keep historical record
            $table->decimal('amount', 18, 2);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_details');
        Schema::dropIfExists('payrolls');
        Schema::dropIfExists('employee_salaries');
        Schema::dropIfExists('salary_components');
    }
};
