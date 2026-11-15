<?php

namespace Modules\ApiManagement\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\HR\App\Models\EmployeePosition;
use Modules\HR\App\Services\OrgChartService;
use Modules\Attendance\App\Services\AttendanceService;

class ExternalHrApiController extends Controller
{
    public function employees(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'unit_id' => ['nullable', 'integer', 'min:1'],
            'employee_type' => ['nullable', 'in:personnel,contractor'],
            'is_active' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = EmployeePosition::query()
            ->with(['user:id,name,employee_type,is_active', 'unit:id,title,level'])
            ->orderBy('personnel_code');

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('personnel_code', 'like', "%{$search}%")
                    ->orWhere('post_title', 'like', "%{$search}%")
                    ->orWhere('job_title', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($user) => $user->where('name', 'like', "%{$search}%"));
            });
        }
        if (!empty($filters['unit_id'])) {
            $query->where('organizational_unit_id', $filters['unit_id']);
        }
        if (isset($filters['employee_type'])) {
            $query->whereHas('user', fn ($user) => $user->where('employee_type', $filters['employee_type']));
        }
        if (array_key_exists('is_active', $filters)) {
            $query->whereHas('user', fn ($user) => $user->where('is_active', $filters['is_active']));
        }

        $employees = $query->paginate($filters['per_page'] ?? 15)->through(function ($position) {
            return [
                'personnel_code' => $position->personnel_code,
                'name' => $position->user?->name,
                'employee_type' => $position->user?->employee_type,
                'is_active' => $position->user?->is_active,
                'post' => ['code' => $position->post_code, 'title' => $position->post_title],
                'job' => ['code' => $position->job_code, 'title' => $position->job_title],
                'unit' => $position->unit ? [
                    'id' => $position->unit->id,
                    'title' => $position->unit->title,
                    'level' => $position->unit->level,
                ] : null,
                'synced_at' => $position->synced_at,
            ];
        });

        return response()->json($employees);
    }

    public function orgChart(OrgChartService $service): JsonResponse
    {
        $tree = $service->buildTree();
        $stripPrivateData = function (array &$nodes) use (&$stripPrivateData): void {
            foreach ($nodes as &$node) {
                if (isset($node['data']) && ($node['data']['type'] ?? null) === 'employee') {
                    unset($node['data']['mobile']);
                }
                if (isset($node['children']) && is_array($node['children'])) {
                    $stripPrivateData($node['children']);
                }
            }
        };
        $stripPrivateData($tree);

        return response()->json(['tree' => $tree, 'stats' => $service->getStats()]);
    }

    public function attendanceEmployees(Request $request, AttendanceService $service): JsonResponse
    {
        return response()->json($service->getTodayAttendanceStatus());
    }
}
