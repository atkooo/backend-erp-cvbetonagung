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
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('role_id')->nullable()->constrained('roles')->nullOnDelete();
            $table->string('action');
            $table->string('object_type');
            $table->uuid('object_id')->nullable();
            $table->string('object_number')->nullable();
            $table->text('summary')->nullable();
            $table->string('ip_address')->nullable();
            $table->timestamp('created_at');

            $table->index(['object_type', 'object_id']);
            $table->index('created_at');
        });

        Schema::create('reminders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->string('reference_type')->nullable();
            $table->uuid('reference_id')->nullable();
            $table->string('reference_number')->nullable();
            $table->string('division')->nullable();
            $table->timestamp('schedule_at')->nullable();
            $table->string('priority')->default('medium');
            $table->string('status')->default('open');
            $table->foreignUuid('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['reference_type', 'reference_id']);
            $table->index(['status', 'schedule_at']);
        });

        Schema::create('document_exports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('document_type');
            $table->string('reference_type')->nullable();
            $table->uuid('reference_id')->nullable();
            $table->string('document_number')->nullable();
            $table->string('export_format');
            $table->string('division')->nullable();
            $table->foreignUuid('exported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('exported_at')->nullable();

            $table->index(['reference_type', 'reference_id']);
            $table->index('exported_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_exports');
        Schema::dropIfExists('reminders');
        Schema::dropIfExists('audit_logs');
    }
};
