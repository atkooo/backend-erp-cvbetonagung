<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\ProjectRequest;
use App\Models\Project;
use App\Models\ProjectBudgetItem;
use App\Models\ProjectDocument;
use App\Models\ProjectTimeline;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ProjectController extends ApiResourceController
{
    /**
     * @var array<string, array{model: class-string<Model>, searchable: array<int, string>, sortable: array<int, string>, relations?: array<int, string>}>
     */
    private const RESOURCES = [
        'projects' => [
            'model' => Project::class,
            'searchable' => ['code', 'project_name', 'location', 'project_type', 'project_spec'],
            'sortable' => ['code', 'project_name', 'deadline', 'progress', 'status', 'contract_value', 'created_at'],
            'relations' => ['customer', 'quotation', 'salesOrder', 'timelines', 'documents', 'budgetItems'],
        ],
        'project-timelines' => [
            'model' => ProjectTimeline::class,
            'searchable' => ['stage', 'description', 'icon'],
            'sortable' => ['event_date', 'stage', 'created_at'],
            'relations' => ['project', 'createdBy'],
        ],
        'project-documents' => [
            'model' => ProjectDocument::class,
            'searchable' => ['title', 'file_url'],
            'sortable' => ['title', 'document_date', 'created_at'],
            'relations' => ['project', 'uploadedBy'],
        ],
        'project-budget-items' => [
            'model' => ProjectBudgetItem::class,
            'searchable' => ['component', 'notes'],
            'sortable' => ['component', 'budget_amount', 'actual_amount', 'created_at'],
            'relations' => ['project'],
        ],
    ];

    /**
     * @return array<string, array{model: class-string<Model>, searchable: array<int, string>, sortable: array<int, string>, relations?: array<int, string>}>
     */
    protected function resources(): array
    {
        return self::RESOURCES;
    }

    public function index(Request $request, string $resource): JsonResponse
    {
        return $this->indexResource($request, $resource);
    }

    public function store(ProjectRequest $request, string $resource): JsonResponse
    {
        return $this->storeResource($resource, $request->validated());
    }

    public function show(string $resource, string $id): JsonResponse
    {
        return $this->showResource($resource, $id);
    }

    public function update(ProjectRequest $request, string $resource, string $id): JsonResponse
    {
        return $this->updateResource($resource, $id, $request->validated());
    }

    public function destroy(string $resource, string $id): JsonResponse|Response
    {
        return $this->destroyResource($resource, $id);
    }

    protected function filterableColumns(): array
    {
        return [
            'customer_id',
            'quotation_id',
            'sales_order_id',
            'project_id',
            'status',
            'project_type',
            'stage',
        ];
    }
}
