<?php

namespace Tests\Feature\Api;

use App\Models\Invoice;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_log_can_be_created_and_listed(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $role = Role::query()->where('code', 'admin')->firstOrFail();
        $invoice = Invoice::query()->where('invoice_number', 'INV-INIT')->firstOrFail();

        $response = $this->postJson('/api/support/audit-logs', [
            'user_id' => $admin->id,
            'role_id' => $role->id,
            'action' => 'updated',
            'object_type' => 'invoice',
            'object_id' => $invoice->id,
            'object_number' => 'INV-API-AUDIT',
            'summary' => 'Invoice updated via API.',
            'ip_address' => '127.0.0.1',
            'created_at' => '2026-06-05 08:00:00',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.object_number', 'INV-API-AUDIT')
            ->assertJsonPath('data.user.email', 'admin@example.com')
            ->assertJsonPath('data.role.code', 'admin');

        $this->getJson('/api/support/audit-logs?object_type=invoice&q=INV-API-AUDIT')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_reminder_and_document_export_can_be_created(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $invoice = Invoice::query()->where('invoice_number', 'INV-INIT')->firstOrFail();

        $this->postJson('/api/support/reminders', [
            'type' => 'invoice_due',
            'reference_type' => 'invoice',
            'reference_id' => $invoice->id,
            'reference_number' => 'INV-API-REM',
            'division' => 'finance',
            'schedule_at' => '2026-06-12 09:00:00',
            'priority' => 'high',
            'status' => 'open',
            'assigned_to' => $admin->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.reference_number', 'INV-API-REM')
            ->assertJsonPath('data.assigned_to.email', 'admin@example.com');

        $this->postJson('/api/support/document-exports', [
            'document_type' => 'invoice',
            'reference_type' => 'invoice',
            'reference_id' => $invoice->id,
            'document_number' => 'INV-API-EXP',
            'export_format' => 'pdf',
            'division' => 'finance',
            'exported_by' => $admin->id,
            'exported_at' => '2026-06-05 10:00:00',
        ])
            ->assertCreated()
            ->assertJsonPath('data.document_number', 'INV-API-EXP')
            ->assertJsonPath('data.exported_by.email', 'admin@example.com');
    }

    public function test_support_api_rejects_invalid_export_format(): void
    {
        $this->seed();

        $this->postJson('/api/support/document-exports', [
            'document_type' => 'invoice',
            'document_number' => 'INV-API-BAD',
            'export_format' => 'docx',
        ])->assertUnprocessable();
    }
}
