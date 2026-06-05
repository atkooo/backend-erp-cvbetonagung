<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\SupportRequest;
use App\Models\AuditLog;
use App\Models\DocumentExport;
use App\Models\Reminder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SupportController extends ApiResourceController
{
    /**
     * @var array<string, array{model: class-string<Model>, searchable: array<int, string>, sortable: array<int, string>, relations?: array<int, string>}>
     */
    private const RESOURCES = [
        'audit-logs' => [
            'model' => AuditLog::class,
            'searchable' => ['action', 'object_type', 'object_number', 'summary', 'ip_address'],
            'sortable' => ['created_at', 'action', 'object_type', 'object_number'],
            'relations' => ['user', 'role'],
        ],
        'reminders' => [
            'model' => Reminder::class,
            'searchable' => ['type', 'reference_type', 'reference_number', 'division'],
            'sortable' => ['schedule_at', 'priority', 'status', 'type', 'created_at'],
            'relations' => ['assignedTo'],
        ],
        'document-exports' => [
            'model' => DocumentExport::class,
            'searchable' => ['document_type', 'reference_type', 'document_number', 'division', 'export_format'],
            'sortable' => ['exported_at', 'document_type', 'document_number', 'export_format'],
            'relations' => ['exportedBy'],
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

    public function store(SupportRequest $request, string $resource): JsonResponse
    {
        return $this->storeResource($resource, $request->validated());
    }

    public function show(string $resource, string $id): JsonResponse
    {
        return $this->showResource($resource, $id);
    }

    public function update(SupportRequest $request, string $resource, string $id): JsonResponse
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
            'user_id',
            'role_id',
            'object_type',
            'reference_type',
            'reference_id',
            'division',
            'priority',
            'status',
            'assigned_to',
            'exported_by',
            'export_format',
        ];
    }
}
