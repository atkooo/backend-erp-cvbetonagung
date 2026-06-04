<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Database\Seeder;

class ErpProjectSeeder extends Seeder
{
    /**
     * Seed a minimal project chain linked to the seeded sales documents.
     */
    public function run(): void
    {
        $customer = Customer::query()->where('code', 'CUST-UMUM')->first();
        $quotation = Quotation::query()->where('quotation_number', 'QUO-INIT')->first();
        $salesOrder = SalesOrder::query()->where('order_number', 'SO-INIT')->first();
        $admin = User::query()->where('email', 'admin@example.com')->first();

        if ($customer === null) {
            return;
        }

        $project = Project::query()->updateOrCreate(
            ['code' => 'PRJ-INIT'],
            [
                'customer_id' => $customer->id,
                'quotation_id' => $quotation?->id,
                'sales_order_id' => $salesOrder?->id,
                'project_name' => 'Proyek Awal CV Beton Agung',
                'location' => 'Area Customer Umum',
                'project_type' => 'installation',
                'project_spec' => 'Baseline seeded project.',
                'contract_value' => 1000000,
                'deadline' => now()->addMonth()->toDateString(),
                'progress' => 0,
                'status' => 'survey',
            ],
        );

        $project->timelines()->updateOrCreate(
            [
                'event_date' => now()->toDateString(),
                'stage' => 'survey',
            ],
            [
                'description' => 'Initial project survey timeline.',
                'icon' => 'clipboard-list',
                'created_by' => $admin?->id,
            ],
        );

        $project->documents()->updateOrCreate(
            ['title' => 'Dokumen Awal Proyek'],
            [
                'file_url' => null,
                'document_date' => now()->toDateString(),
                'uploaded_by' => $admin?->id,
            ],
        );

        $project->budgetItems()->updateOrCreate(
            ['component' => 'Material dan Produksi'],
            [
                'budget_amount' => 750000,
                'actual_amount' => 0,
                'notes' => 'Baseline project budget component.',
            ],
        );
    }
}
