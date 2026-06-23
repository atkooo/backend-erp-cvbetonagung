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
        Schema::create('bags', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('bag_number')->unique();
            $table->date('date');
            $table->uuid('warehouse_id');
            $table->uuid('location_id')->nullable();
            $table->string('type'); // in, out, adjustment
            $table->text('notes')->nullable();
            $table->string('status')->default('Final'); // Draft, Final
            $table->uuid('created_by')->nullable();
            $table->timestamps();

            $table->foreign('warehouse_id')->references('id')->on('warehouses')->onDelete('cascade');
            $table->foreign('location_id')->references('id')->on('storage_locations')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bags');
    }
};
