<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Project;
use App\Models\ProjectBudgetItem;
use App\Models\ProjectDocument;
use App\Models\ProjectTimeline;
use App\Models\SalesOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ErpProjectTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_seed_creates_project_and_children(): void
    {
        $this->seed();

        $this->assertDatabaseHas('projects', ['code' => 'PRJ-INIT']);
        $this->assertDatabaseHas('project_timelines', ['stage' => 'survey']);
        $this->assertDatabaseHas('project_documents', ['title' => 'Dokumen Awal Proyek']);
        $this->assertDatabaseHas('project_budget_items', ['component' => 'Material dan Produksi']);
    }

    public function test_project_relations_are_available(): void
    {
        $this->seed();

        $project = Project::query()->where('code', 'PRJ-INIT')->firstOrFail();

        $this->assertSame('CUST-UMUM', $project->customer->code);
        $this->assertSame('QUO-INIT', $project->quotation?->quotation_number);
        $this->assertSame('SO-INIT', $project->salesOrder?->order_number);
        $this->assertSame('1000000.00', $project->contract_value);
    }

    public function test_project_child_relations_are_available(): void
    {
        $this->seed();

        $project = Project::query()->where('code', 'PRJ-INIT')->firstOrFail();
        $timeline = ProjectTimeline::query()->firstOrFail();
        $document = ProjectDocument::query()->firstOrFail();
        $budgetItem = ProjectBudgetItem::query()->firstOrFail();

        $this->assertTrue($project->timelines->contains('stage', 'survey'));
        $this->assertTrue($project->documents->contains('title', 'Dokumen Awal Proyek'));
        $this->assertTrue($project->budgetItems->contains('component', 'Material dan Produksi'));
        $this->assertSame('admin@example.com', $timeline->createdBy?->email);
        $this->assertSame('admin@example.com', $document->uploadedBy?->email);
        $this->assertSame('750000.00', $budgetItem->budget_amount);
    }

    public function test_customer_and_sales_order_can_reach_projects(): void
    {
        $this->seed();

        $customer = Customer::query()->where('code', 'CUST-UMUM')->firstOrFail();
        $salesOrder = SalesOrder::query()->where('order_number', 'SO-INIT')->firstOrFail();

        $this->assertTrue($customer->projects->contains('code', 'PRJ-INIT'));
        $this->assertTrue($salesOrder->projects->contains('code', 'PRJ-INIT'));
    }
}
