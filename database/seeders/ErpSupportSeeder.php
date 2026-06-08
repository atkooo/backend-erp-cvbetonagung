<?php

namespace Database\Seeders;

use App\Models\AuditLog;
use App\Models\DocumentExport;
use App\Models\Invoice;
use App\Models\Reminder;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class ErpSupportSeeder extends Seeder
{
    /**
     * Seed support/reporting examples.
     */
    public function run(): void
    {
        $admin = User::query()->where('email', 'admin@example.com')->first();
        $adminRole = Role::query()->where('code', 'admin')->first();
        $invoice = Invoice::query()->where('invoice_number', 'INV-INIT')->first();

        if ($admin === null || $invoice === null) {
            return;
        }

        AuditLog::query()->updateOrCreate(
            [
                'action' => 'created',
                'object_type' => 'invoice',
                'object_number' => $invoice->invoice_number,
            ],
            [
                'user_id' => $admin->id,
                'role_id' => $adminRole?->id,
                'object_id' => $invoice->id,
                'summary' => 'Initial invoice audit baseline.',
                'ip_address' => '127.0.0.1',
                'created_at' => now(),
            ],
        );

        Reminder::query()->updateOrCreate(
            [
                'type' => 'invoice_due',
                'reference_number' => $invoice->invoice_number,
            ],
            [
                'reference_type' => 'invoice',
                'reference_id' => $invoice->id,
                'division' => 'finance',
                'schedule_at' => now()->addDays(7),
                'priority' => 'medium',
                'status' => 'open',
                'assigned_to' => $admin->id,
            ],
        );

        DocumentExport::query()->updateOrCreate(
            [
                'document_type' => 'invoice',
                'document_number' => $invoice->invoice_number,
                'export_format' => 'pdf',
            ],
            [
                'reference_type' => 'invoice',
                'reference_id' => $invoice->id,
                'division' => 'finance',
                'exported_by' => $admin->id,
                'exported_at' => now(),
            ],
        );
    }
}
