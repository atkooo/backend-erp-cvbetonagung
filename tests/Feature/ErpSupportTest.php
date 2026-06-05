<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\DocumentExport;
use App\Models\Reminder;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ErpSupportTest extends TestCase
{
    use RefreshDatabase;

    public function test_support_seed_creates_audit_reminder_and_export_records(): void
    {
        $this->seed();

        $this->assertDatabaseHas('audit_logs', ['object_number' => 'INV-INIT']);
        $this->assertDatabaseHas('reminders', ['reference_number' => 'INV-INIT']);
        $this->assertDatabaseHas('document_exports', ['document_number' => 'INV-INIT']);
    }

    public function test_audit_log_relations_are_available(): void
    {
        $this->seed();

        $auditLog = AuditLog::query()->where('object_number', 'INV-INIT')->firstOrFail();

        $this->assertSame('admin@example.com', $auditLog->user?->email);
        $this->assertSame('admin', $auditLog->role?->code);
        $this->assertSame('invoice', $auditLog->object_type);
    }

    public function test_reminder_and_export_relations_are_available(): void
    {
        $this->seed();

        $reminder = Reminder::query()->where('reference_number', 'INV-INIT')->firstOrFail();
        $export = DocumentExport::query()->where('document_number', 'INV-INIT')->firstOrFail();

        $this->assertSame('admin@example.com', $reminder->assignedTo?->email);
        $this->assertSame('admin@example.com', $export->exportedBy?->email);
        $this->assertSame('medium', $reminder->priority);
        $this->assertSame('pdf', $export->export_format);
    }

    public function test_user_and_role_can_reach_support_records(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $role = Role::query()->where('code', 'admin')->firstOrFail();

        $this->assertTrue($admin->auditLogs->contains('object_number', 'INV-INIT'));
        $this->assertTrue($admin->reminders->contains('reference_number', 'INV-INIT'));
        $this->assertTrue($admin->documentExports->contains('document_number', 'INV-INIT'));
        $this->assertTrue($role->auditLogs->contains('object_number', 'INV-INIT'));
    }
}
