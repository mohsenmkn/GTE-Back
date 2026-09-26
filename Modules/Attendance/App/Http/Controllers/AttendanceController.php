<?php

// Modules/Attendance/App/Http/Controllers/AttendanceController.php

namespace Modules\Attendance\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use Modules\Attendance\App\Services\AttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function __construct(
        private AttendanceService $attendanceService
    ) {}

    /**
     * GET /api/attendance/summary
     * خلاصه تردد کاربر فعلی
     */
    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();

        if (empty($user->personnel_code)) {
            return response()->json([
                'message' => 'کد پرسنلی تعریف نشده است.',
                'data' => [],
            ], 404);
        }

        $summaries = $this->attendanceService->getAttendanceSummary($user->personnel_code);

        return response()->json([
            'data' => array_map(fn($s) => $s->toArray(), $summaries),
        ]);
    }

    /**
     * 🔑 GET /api/attendance/employees
     * لیست پرسنل با خلاصه تردد (برای مدیران HR)
     */
    public function employees(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermissionTo('AdminHr.view')) {
            return response()->json(['message' => 'دسترسی ندارید.'], 403);
        }

        $search = $request->query('search', '');
        $month = $request->query('month', ''); // اختیاری: فیلتر ماه

        $result = $this->attendanceService->getAllEmployeesAttendance($search, $month);

        return response()->json([
            'data' => $result['data'],
            'meta' => $result['meta'],
        ]);
    }


    public function summaryForEmployee(Request $request, string $personnelCode): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermissionTo('AdminHr.view')) {
            return response()->json(['message' => 'دسترسی ندارید.'], 403);
        }

        $summaries = $this->attendanceService->getAttendanceSummary($personnelCode);

        return response()->json([
            'data' => array_map(fn($s) => $s->toArray(), $summaries),
        ]);
    }



    /**
     * 🔑 GET /api/attendance/employees/{personnelCode}/daily
     * جزئیات روزانه تردد یک کارمند
     */
    public function dailyAttendance(Request $request, string $personnelCode): JsonResponse
    {
        if (!$request->user()->can('AdminHr.view')) {
            return response()->json(['message' => 'دسترسی ندارید.'], 403);
        }

        $month = $request->query('month', '');

        $result = $this->attendanceService->getDailyAttendance($personnelCode, $month);

        return response()->json([
            'data' => $result['daily'],
            'summary' => $result['summary'],  // 🔑 اضافه شد
        ]);
    }

    /**
     * 🔑 GET /api/attendance/employees/{personnelCode}/months
     * لیست ماه‌های موجود برای یک کارمند
     */
    public function availableMonths(Request $request, string $personnelCode): JsonResponse
    {
        if (!$request->user()->can('AdminHr.view')) {
            return response()->json(['message' => 'دسترسی ندارید.'], 403);
        }

        $months = $this->attendanceService->getAvailableMonths($personnelCode);

        return response()->json([
            'data' => $months,
        ]);
    }

    /**
     * GET /api/v1/attendance/latest
     *
     * آخرین ورود و خروج کاربر فعلی
     */
    public function latest(Request $request): JsonResponse
    {
        $user = $request->user();

        if (empty($user->personnel_code)) {
            return response()->json([
                'message' => 'کد پرسنلی تعریف نشده است.',
                'data' => null,
            ], 404);
        }
        //Log::info("Persoonel code is: " . $user->personnel_code);
        $data = $this->attendanceService
            ->getLatestAttendance($user->personnel_code);

        return response()->json([
            'data' => $data,
        ]);
    }





}
