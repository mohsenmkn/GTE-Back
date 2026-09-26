<?php

namespace Modules\HR\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\HR\App\Models\EmployeeTraining;
use Modules\HR\App\Services\EmployeeService;
use Modules\HR\App\Services\TrainingSyncService;

class EmployeeController extends Controller
{
    public function __construct(
        private EmployeeService $employeeService
    ) {}

    /**
     * GET /api/v1/hr/employees
     * لیست پرسنل با فیلتر و pagination
     */

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only([
            'search',
            'unit_id',
            'employee_type',
            'is_active',
        ]);

        $perPage = min((int) $request->input('per_page', 15), 50);

        $employees = $this->employeeService->getEmployeesList($filters, $perPage);

        return response()->json($employees);
    }

    /**
     * GET /api/v1/hr/employees/{user}
     * پروفایل یک کارمند
     */
    public function show(int $user): JsonResponse
    {
        $profile = $this->employeeService->getEmployeeProfile($user);

        if (!$profile) {
            return response()->json([
                'message' => 'کارمند یافت نشد.',
            ], 404);
        }

        return response()->json(['employee' => $profile]);
    }


    /**
     * GET /api/v1/hr/employees/{user}/relatives
     * اطلاعات خانواده کارمند
     */
    public function relatives(int $user): JsonResponse
    {
        $relatives = $this->employeeService->getEmployeeRelatives($user);

        return response()->json([
            'relatives' => $relatives,
            'total'     => count($relatives),
        ]);
    }


    /**
     * GET /api/v1/hr/employees/{user}/history
     * تاریخچه احکام و سمت‌های کارمند
     */
    public function history(int $user): JsonResponse
    {
        $history = $this->employeeService->getStatuteHistory($user);

        return response()->json([
            'history' => $history,
            'total'   => count($history),
        ]);
    }


    /**
     * GET /api/v1/hr/employees/{user}/history/details
     * جزئیات احکام یک دوره خاص
     */
    /**
     * GET /api/v1/hr/employees/{user}/history/details
     * جزئیات احکام یک دوره خاص
     */
    public function historyDetails(Request $request, int $user): JsonResponse
    {
        $validated = $request->validate([
            'post_ref' => 'required|integer',
            'job_ref'  => 'required|integer',
        ], [
            'post_ref.required' => 'post_ref الزامی است.',
            'job_ref.required'  => 'job_ref الزامی است.',
        ]);

        $details = $this->employeeService->getStatuteHistoryDetails(
            $user,
            (int) $validated['post_ref'],
            (int) $validated['job_ref']
        );

        return response()->json([
            'details' => $details,
            'total'   => count($details),
        ]);
    }

    /**
     * GET /api/v1/hr/employees/{user}/attendance
     * اطلاعات تردد کارمند
     */
    public function attendance(Request $request, int $user): JsonResponse
    {
        $month = $request->query('month');

        $data = $this->employeeService->getEmployeeAttendance($user, $month);

        if (isset($data['error'])) {
            return response()->json(['message' => $data['error']], 404);
        }

        return response()->json($data);
    }

    /**
     * GET /api/v1/hr/employees/{user}/training
     * لیست دوره‌های آموزشی کارمند
     */
    public function training(int $user): JsonResponse
    {
        $trainings = EmployeeTraining::where('user_id', $user)
            ->orderByDesc('synced_at')
            ->orderByDesc('performance_hours')
            ->get()
            ->map(function ($t) {
                return [
                    'id'                => $t->id,
                    'course_code'       => $t->course_code,
                    'course_title'      => $t->course_title,
                    'deputy'            => $t->deputy,
                    'management'        => $t->management,
                    'post_title'        => $t->post_title,
                    'session_duration'  => $t->session_duration,
                    'session_hours'     => round($t->session_hours, 2),
                    'performance_hours' => (float) $t->performance_hours,
                    'status'            => $t->status_title,
                    'status_severity'   => $t->status_severity,
                    'synced_at'         => $t->synced_at?->format('Y-m-d H:i'),
                    'date_label'        => $t->date_label,
                ];
            });

        // آمار کلی
        $totalHours = $trainings->sum('performance_hours');
        $totalCourses = $trainings->count();

        return response()->json([
            'trainings' => $trainings,
            'summary'   => [
                'total_courses'    => $totalCourses,
                'total_hours'      => round($totalHours, 2),
                'completed_count'  => $trainings->where('status', 'تکمیل شده')->count(),
                'in_progress_count'=> $trainings->where('status', 'در حال برگزاری')->count(),
            ],
        ]);
    }

    /**
     * POST /api/v1/hr/employees/{user}/training/sync
     * sync دستی آموزش‌های یک کاربر
     */
    public function syncTraining(int $user): JsonResponse
    {
        $userModel = \Modules\Auth\App\Models\User::findOrFail($user);

        $syncService = app(TrainingSyncService::class);
        $count = $syncService->syncUser($userModel, false);

        return response()->json([
            'message' => "sync با موفقیت انجام شد. {$count} دوره همگام‌سازی شد.",
            'synced_count' => $count,
        ]);
    }

    /**
     * POST /api/v1/hr/employees/{user}/training/enrich-dates
     * تکمیل تاریخ دوره‌ها با دقت ماه (On-Demand)
     */
    public function enrichTrainingDates(int $user): JsonResponse
    {
        $userModel = \Modules\Auth\App\Models\User::findOrFail($user);

        $service = app(\Modules\HR\App\Services\TrainingSyncService::class);
        $stats = $service->enrichDatesForUser($userModel, true);

        return response()->json([
            'message' => 'تاریخ دوره‌ها تکمیل شد.',
            'stats'   => $stats,
        ]);
    }


}
