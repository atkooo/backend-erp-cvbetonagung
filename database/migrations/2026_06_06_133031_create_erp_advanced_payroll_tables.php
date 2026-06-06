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
        Schema::create('employee_loans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('loan_number')->unique();
            $table->decimal('amount', 18, 2);
            $table->string('reason')->nullable();
            $table->date('date');
            $table->string('status')->default('pending'); // pending, approved, rejected, paid
            $table->decimal('remaining_amount', 18, 2);
            $table->decimal('installment_amount', 18, 2);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('overtime_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->decimal('rate_per_hour', 18, 2);
            $table->string('type')->default('weekday'); // weekday, weekend, holiday
            $table->timestamps();
        });

        Schema::create('payroll_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('key')->unique();
            $table->json('value');
            $table->string('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_settings');
        Schema::dropIfExists('overtime_rules');
        Schema::dropIfExists('employee_loans');
    }
};
