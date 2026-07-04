<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\HrdRequest;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\EmployeeDetail;
use App\Models\EmployeeDocument;
use App\Models\Leave;
use App\Models\LeaveType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class HrdController extends ApiResourceController
{
    /**
     * @var array<string, array{model: class-string<Model>, searchable: array<int, string>, sortable: array<int, string>, relations?: array<int, string>}>
     */
    private const RESOURCES = [
        'employee-details' => [
            'model' => EmployeeDetail::class,
            'searchable' => ['name', 'type', 'institution'],
            'sortable' => ['type', 'name', 'created_at'],
            'relations' => ['employee'],
        ],
        'employee-documents' => [
            'model' => EmployeeDocument::class,
            'searchable' => ['document_type', 'file_name'],
            'sortable' => ['document_type', 'expiry_date', 'created_at'],
            'relations' => ['employee'],
        ],
        'leave-types' => [
            'model' => LeaveType::class,
            'searchable' => ['code', 'name'],
            'sortable' => ['code', 'name', 'is_paid', 'max_days'],
            'relations' => [],
        ],
        'leaves' => [
            'model' => Leave::class,
            'searchable' => ['status', 'reason'],
            'sortable' => ['start_date', 'end_date', 'status', 'created_at'],
            'relations' => ['employee', 'leaveType', 'approver'],
        ],
        'attendances' => [
            'model' => Attendance::class,
            'searchable' => ['status', 'notes'],
            'sortable' => ['date', 'clock_in', 'clock_out', 'late_minutes'],
            'relations' => ['employee'],
        ],
    ];

    /**
     * @return array<string, array{model: class-string<Model>, searchable: array<int, string>, sortable: array<int, string>, relations?: array<int, string>}>
     */
    protected function resources(): array
    {
        return self::RESOURCES;
    }

    public function scanAttendance(Request $request): JsonResponse
    {
        // Simulate auth by requiring employee_id, and expecting a location QR code
        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'location_qr' => 'required|string',
        ]);

        $employee = Employee::find($request->employee_id);
        $locationQr = $request->location_qr;

        // In a real app, you would validate if $locationQr is a valid office QR.
        if ($locationQr !== 'QR-OFFICE-MAIN-1') {
            return response()->json(['message' => 'QR Code lokasi tidak valid atau kadaluarsa.'], 400);
        }

        $today = now()->toDateString();
        $currentTime = now()->toTimeString();

        $attendance = Attendance::where('employee_id', $employee->id)
            ->where('date', $today)
            ->first();

        if (! $attendance) {
            $shiftStart = '08:00:00';
            $lateMinutes = 0;
            if ($currentTime > $shiftStart) {
                $lateMinutes = now()->diffInMinutes($today.' '.$shiftStart);
            }

            $attendance = Attendance::create([
                'employee_id' => $employee->id,
                'date' => $today,
                'clock_in' => $currentTime,
                'status' => $lateMinutes > 0 ? 'late' : 'present',
                'late_minutes' => $lateMinutes,
            ]);

            return response()->json([
                'message' => 'Clock In berhasil.',
                'employee_name' => $employee->name,
                'time' => $currentTime,
                'type' => 'clock_in',
            ]);
        } else {
            if ($attendance->clock_out) {
                return response()->json([
                    'message' => 'Anda sudah melakukan Clock Out hari ini.',
                    'employee_name' => $employee->name,
                    'time' => $attendance->clock_out,
                    'type' => 'already_clocked_out',
                ], 400);
            }

            $attendance->update([
                'clock_out' => $currentTime,
            ]);

            return response()->json([
                'message' => 'Clock Out berhasil.',
                'employee_name' => $employee->name,
                'time' => $currentTime,
                'type' => 'clock_out',
            ]);
        }
    }

    public function index(Request $request, string $resource): JsonResponse
    {
        return $this->indexResource($request, $resource);
    }

    public function store(HrdRequest $request, string $resource): JsonResponse
    {
        return $this->storeResource($resource, $request->validated());
    }

    public function show(string $resource, string $id): JsonResponse
    {
        return $this->showResource($resource, $id);
    }

    public function update(HrdRequest $request, string $resource, string $id): JsonResponse
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
            'employee_id',
            'leave_type_id',
            'status',
            'date',
        ];
    }
}
