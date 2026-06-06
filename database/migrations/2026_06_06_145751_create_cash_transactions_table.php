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
        Schema::create('cash_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('transaction_number')->unique(); // e.g. TR-20231024-001
            $table->uuid('account_id');
            $table->date('transaction_date');
            $table->string('type'); // in (uang masuk), out (uang keluar)
            $table->decimal('amount', 15, 2);
            $table->string('category'); // e.g. operational, payroll, utility, tax, etc.
            $table->text('description')->nullable();
            $table->string('reference_type')->nullable(); // e.g. Invoice, Payment, Payroll
            $table->uuid('reference_id')->nullable();
            $table->uuid('recorded_by')->nullable();
            $table->timestamps();

            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_transactions');
    }
};
