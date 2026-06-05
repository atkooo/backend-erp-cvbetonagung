<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_can_be_created_listed_and_updated(): void
    {
        $this->seed();

        $customer = Customer::query()->where('code', 'CUST-UMUM')->firstOrFail();
        $quotation = Quotation::query()->where('quotation_number', 'QUO-INIT')->firstOrFail();
        $salesOrder = SalesOrder::query()->where('order_number', 'SO-INIT')->firstOrFail();

        $response = $this->postJson('/api/projects/projects', [
            'code' => 'PRJ-API-001',
            'customer_id' => $customer->id,
            'quotation_id' => $quotation->id,
            'sales_order_id' => $salesOrder->id,
            'project_name' => 'API Project',
            'location' => 'Jakarta',
            'project_type' => 'installation',
            'project_spec' => 'API project spec.',
            'contract_value' => 2000000,
            'deadline' => '2026-07-05',
            'progress' => 10,
            'status' => 'survey',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.code', 'PRJ-API-001')
            ->assertJsonPath('data.customer.code', 'CUST-UMUM')
            ->assertJsonPath('data.sales_order.order_number', 'SO-INIT');

        $id = $response->json('data.id');

        $this->getJson('/api/projects/projects?q=API Project')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->patchJson("/api/projects/projects/{$id}", [
            'progress' => 35,
            'status' => 'production',
        ])
            ->assertOk()
            ->assertJsonPath('data.progress', 35)
            ->assertJsonPath('data.status', 'production');
    }

    public function test_project_child_records_can_be_created(): void
    {
        $this->seed();

        $project = Project::query()->where('code', 'PRJ-INIT')->firstOrFail();
        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();

        $this->postJson('/api/projects/project-timelines', [
            'project_id' => $project->id,
            'event_date' => '2026-06-05',
            'stage' => 'production',
            'description' => 'Production started via API.',
            'icon' => 'factory',
            'created_by' => $admin->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.project.code', 'PRJ-INIT')
            ->assertJsonPath('data.created_by.email', 'admin@example.com');

        $this->postJson('/api/projects/project-documents', [
            'project_id' => $project->id,
            'title' => 'Dokumen API',
            'file_url' => 'https://example.com/project-document.pdf',
            'document_date' => '2026-06-05',
            'uploaded_by' => $admin->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.project.code', 'PRJ-INIT')
            ->assertJsonPath('data.uploaded_by.email', 'admin@example.com');

        $this->postJson('/api/projects/project-budget-items', [
            'project_id' => $project->id,
            'component' => 'API Budget Component',
            'budget_amount' => 300000,
            'actual_amount' => 125000,
            'notes' => 'Created via API.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.project.code', 'PRJ-INIT')
            ->assertJsonPath('data.component', 'API Budget Component');
    }

    public function test_project_api_rejects_invalid_progress_and_status(): void
    {
        $this->seed();

        $customer = Customer::query()->where('code', 'CUST-UMUM')->firstOrFail();

        $this->postJson('/api/projects/projects', [
            'code' => 'PRJ-API-BAD',
            'customer_id' => $customer->id,
            'project_name' => 'Bad Project',
            'progress' => 150,
            'status' => 'processing',
        ])->assertUnprocessable();
    }
}
