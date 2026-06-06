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
        // Add HRD columns to existing employees table
        Schema::table('employees', function (Blueprint $table) {
            $table->string('gender')->nullable()->after('name');
            $table->string('place_of_birth')->nullable()->after('gender');
            $table->date('date_of_birth')->nullable()->after('place_of_birth');
            $table->string('marital_status')->nullable()->after('date_of_birth');
            $table->string('religion')->nullable()->after('marital_status');
            $table->string('blood_type')->nullable()->after('religion');
            $table->string('id_card_number')->nullable()->after('blood_type');
            $table->string('tax_id_number')->nullable()->after('id_card_number');
            $table->string('bank_name')->nullable()->after('tax_id_number');
            $table->string('bank_account')->nullable()->after('bank_name');
        });

        // 1. Employee Details (Family, Education, Emergency Contacts)
        Schema::create('employee_details', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('type'); // 'family', 'education', 'emergency_contact'
            $table->string('name');
            $table->string('relation')->nullable(); // For family/emergency
            $table->string('phone')->nullable();
            $table->string('institution')->nullable(); // For education
            $table->string('degree')->nullable(); // For education
            $table->string('year_start')->nullable();
            $table->string('year_end')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 2. Employee Documents
        Schema::create('employee_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('document_type'); // KTP, KK, Ijazah, Kontrak
            $table->string('file_path');
            $table->string('file_name');
            $table->date('expiry_date')->nullable();
            $table->timestamps();
        });

        // 3. Leave Types
        Schema::create('leave_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code')->unique();
            $table->string('name');
            $table->boolean('is_paid')->default(true);
            $table->integer('max_days')->default(0); // 0 means no max limit, usually annual leave is 12
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // 4. Leaves (Cuti/Izin)
        Schema::create('leaves', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignUuid('leave_type_id')->constrained('leave_types')->restrictOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->integer('total_days');
            $table->text('reason')->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('status')->default('pending'); // pending, approved, rejected
            $table->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        // 5. Attendances
        Schema::create('attendances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('date');
            $table->time('clock_in')->nullable();
            $table->time('clock_out')->nullable();
            $table->string('status')->default('present'); // present, late, absent, half_day, leave
            $table->decimal('late_minutes', 8, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendances');
        Schema::dropIfExists('leaves');
        Schema::dropIfExists('leave_types');
        Schema::dropIfExists('employee_documents');
        Schema::dropIfExists('employee_details');

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn([
                'gender', 'place_of_birth', 'date_of_birth', 'marital_status', 
                'religion', 'blood_type', 'id_card_number', 'tax_id_number', 
                'bank_name', 'bank_account'
            ]);
        });
    }
};
