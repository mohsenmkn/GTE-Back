<?php

namespace Modules\HR\App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Auth\App\Models\User;
use Modules\HR\App\Models\EmployeePosition;
use Modules\HR\App\Models\EmployeeRelative;
use Modules\HR\App\Models\EmployeeStatuteHistory;
use Modules\HR\App\Models\EmployeeTraining;

class EmployeeService
{
    /**
     * لیست پرسنل با فیلتر و pagination
     */
    /**
     * لیست پرسنل با فیلتر و pagination
     */
    public function getEmployeesList(array $filters = [], int $perPage = 15)
    {
        $query = EmployeePosition::with([
            'user:id,name,mobile,email,employee_type,is_active',
            'unit:id,title,level'
        ])
            ->orderBy('personnel_code');

        // جستجو
        if (!empty($filters['search'])) {
            $search = $filters['search'];

            $query->where(function ($q) use ($search) {
                $q->where('personnel_code', 'LIKE', "%{$search}%")
                    ->orWhere('post_title', 'LIKE', "%{$search}%")
                    ->orWhere('job_title', 'LIKE', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('name', 'LIKE', "%{$search}%")
                            ->orWhere('mobile', 'LIKE', "%{$search}%");
                    });
            });
        }

        // فیلتر واحد سازمانی
        if (!empty($filters['unit_id'])) {
            $query->where('organizational_unit_id', $filters['unit_id']);
        }

        // ✅ فیلتر نوع کاربر (پرسنل / پیمانکار)
        if (!empty($filters['employee_type'])) {
            $query->whereHas('user', function ($q) use ($filters) {
                $q->where('employee_type', $filters['employee_type']);
            });
        }

        // ✅ فیلتر وضعیت (فعال / غیرفعال)
        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query->whereHas('user', function ($q) use ($filters) {
                $q->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
            });
        }

        return $query->paginate($perPage);
    }

    /**
     * پروفایل کامل یک کارمند
     */
    public function getEmployeeProfile(int $userId): ?array
    {
        $position = EmployeePosition::with(['user', 'unit'])
            ->where('user_id', $userId)
            ->first();

        if (!$position) return null;

        $user = $position->user;

        // اطلاعات استخدام از گستراب (live)
        $employment = $this->getEmploymentInfo($position->gt_employee_id);

        return [
            'user_id'        => $user->id,
            'name'           => $user->name,
            'mobile'         => $user->mobile,
            'email'          => $user->email,
            'national_code'  => $user->national_code,
            'personnel_code' => $user->personnel_code,

            'post' => [
                'code'  => $position->post_code,
                'title' => $position->post_title,
            ],
            'job' => [
                'code'  => $position->job_code,
                'title' => $position->job_title,
            ],
            'unit' => $position->unit ? [
                'id'         => $position->unit->id,
                'title'      => $position->unit->title,
                'level'      => $position->unit->level,
                'breadcrumb' => $position->unit->breadcrumb,
            ] : null,

            'employment' => $employment,

            'synced_at'   => $position->synced_at,
            'sync_failed' => $position->sync_failed,
        ];
    }

    /**
     * اطلاعات استخدام از گستراب
     */
    private function getEmploymentInfo(?int $employeeId): ?array
    {
        if (!$employeeId) return null;

        try {
            $employee = DB::connection('gtarabar')
                ->table('HCM3.Employee')
                ->where('EmployeeID', $employeeId)
                ->select('EmploymentNumber', 'CreationDate', 'Status', 'DeathDate')
                ->first();

            if (!$employee) return null;

            $statute = DB::connection('gtarabar')
                ->table('HCM3.EmployeeStatute')
                ->where('EmployeeRef', $employeeId)
                ->orderBy('EmployeeStatuteID', 'desc')
                ->select('IssueDate', 'Number', 'Status', 'ExpiryDate')
                ->first();

            return [
                'employment_number'   => $employee->EmploymentNumber,
                'employment_date'     => $employee->CreationDate,
                'last_statute_date'   => $statute?->IssueDate,
                'last_statute_number' => $statute?->Number,
                'is_active'           => $this->determineActiveStatus($employee, $statute),
            ];
        } catch (\Exception $e) {
            Log::error("getEmploymentInfo({$employeeId}) failed: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * تشخیص وضعیت فعال بودن کارمند
     *
     * ⚠️ Status جدول Employee قابل اعتماد نیست.
     * منطق: DeathDate + حکم فعال
     */
    private function determineActiveStatus(object $employee, ?object $statute): bool
    {
        // فوت شده → غیرفعال
        if (!empty($employee->DeathDate)) {
            return false;
        }

        // حکم فعال بدون تاریخ انقضا → فعال
        if ($statute && empty($statute->ExpiryDate)) {
            return true;
        }

        // حکم با تاریخ انقضا در آینده → هنوز فعال
        if ($statute && !empty($statute->ExpiryDate)) {
            return strtotime($statute->ExpiryDate) > time();
        }

        // بدون حکم یا منقضی → غیرفعال
        return false;
    }

    /**
     * اطلاعات خانواده کارمند از جدول محلی
     * ترجمه کدها در accessor های Model EmployeeRelative انجام می‌شود
     */
    public function getEmployeeRelatives(int $userId): array
    {
        return EmployeeRelative::where('user_id', $userId)
            ->orderBy('relation_code')
            ->orderBy('birth_date')
            ->get()
            ->map(function ($r) {
                return [
                    'EmployeeRelativeID'  => $r->gt_relative_id,
                    'FirstName'           => $r->first_name,
                    'LastName'            => $r->last_name,
                    'FullName'            => $r->full_name,
                    'FatherName'          => $r->father_name,
                    'RelationCode'        => $r->relation_code,
                    'RelationTitle'       => $r->relation_title,
                    'NationalID'          => $r->national_id,
                    'IDNumber'            => $r->id_number,
                    'BirthDate'           => $r->birth_date?->format('Y-m-d'),
                    'Age'                 => $r->age,
                    'DegreeTitle'         => $r->degree_title,
                    'EducationStateTitle' => $r->education_state_title,
                    'PhysicalStateTitle'  => $r->physical_state_title,
                    'MaritalStatusTitle'  => $r->marital_status_title,
                    'Job'                 => $r->job,
                    'Description'         => $r->description,
                ];
            })
            ->toArray();
    }



    /**
     * تاریخچه احکام و سمت‌های کارمند
     */
    /**
     * تاریخچه احکام کارمند — گروه‌بندی شده بر اساس سمت و شغل
     * سمت‌های تکراری ادغام شده و بازه زمانی هر سمت محاسبه می‌شود


    /**
     * تاریخچه احکام کارمند — گروه‌بندی شده بر اساس سمت و شغل
     */
    public function getStatuteHistory(int $userId): array
    {
        $groups = EmployeeStatuteHistory::where('user_id', $userId)
            ->select(
                'post_ref',
                'post_code',
                'post_title',
                'job_ref',
                'job_code',
                'job_title',
                DB::raw('MIN(issue_date) AS start_date'),
                DB::raw('MAX(issue_date) AS end_date'),
                DB::raw('COUNT(*) AS statute_count'),
                DB::raw('MAX(CASE WHEN is_current = 1 THEN 1 ELSE 0 END) AS is_current'),
                DB::raw('MAX(gt_statute_id) AS latest_statute_id'),
                DB::raw('MAX(CASE WHEN is_current = 1 THEN statute_number ELSE NULL END) AS latest_statute_number')
            )
            ->groupBy(
                'post_ref', 'post_code', 'post_title',
                'job_ref', 'job_code', 'job_title'
            )
            ->orderByDesc('start_date')
            ->get();

        return $groups->map(function ($g) {
            $startDate = $g->start_date ? Carbon::parse($g->start_date) : null;
            $endDate   = $g->end_date ? Carbon::parse($g->end_date) : null;

            return [
                // ✅ این دو فیلد برای Dialog جزئیات ضروری هستند
                'post_ref'        => $g->post_ref,
                'job_ref'         => $g->job_ref,

                'post_title'      => $g->post_title,
                'post_code'       => $g->post_code,
                'job_title'       => $g->job_title,
                'job_code'        => $g->job_code,
                'start_date'      => $startDate?->format('Y-m-d'),
                'end_date'        => $endDate?->format('Y-m-d'),
                'statute_count'   => (int) $g->statute_count,
                'is_current'      => (bool) $g->is_current,
                'latest_statute_id'     => $g->latest_statute_id,
                'latest_statute_number' => $g->latest_statute_number,
                'duration'        => $this->calculateDuration($startDate, $endDate, (bool) $g->is_current),
            ];
        })->toArray();
    }

    /**
     * محاسبه مدت زمان یک سمت
     */
    private function calculateDuration(?Carbon $startDate, ?Carbon $endDate, bool $isCurrent): ?string
    {
        if (!$startDate) return null;

        $end = $isCurrent ? now() : ($endDate ?? now());

        if ($end->lt($startDate)) {
            $end = now();
        }

        $diff = $startDate->diff($end);

        $parts = [];
        if ($diff->y > 0) $parts[] = $diff->y . ' سال';
        if ($diff->m > 0) $parts[] = $diff->m . ' ماه';
        if (empty($parts) && $diff->d > 0) $parts[] = $diff->d . ' روز';

        return implode(' و ', $parts) ?: 'کمتر از یک ماه';
    }



    /**
     * جزئیات احکام یک دوره خاص (برای نمایش در Dialog)
     */
    public function getStatuteHistoryDetails(int $userId, int $postRef, int $jobRef): array
    {
        return EmployeeStatuteHistory::where('user_id', $userId)
            ->where('post_ref', $postRef)
            ->where('job_ref', $jobRef)
            ->orderBy('issue_date', 'desc')
            ->get()
            ->map(function ($h) {
                return [
                    'gt_statute_id'  => $h->gt_statute_id,
                    'post_title'     => $h->post_title,
                    'post_code'      => $h->post_code,
                    'job_title'      => $h->job_title,
                    'job_code'       => $h->job_code,
                    'issue_date'     => $h->issue_date?->format('Y-m-d'),
                    'apply_date'     => $h->apply_date?->format('Y-m-d'),
                    'expiry_date'    => $h->expiry_date?->format('Y-m-d'),
                    'statute_number' => $h->statute_number,
                    'is_current'     => $h->is_current,
                ];
            })
            ->toArray();
    }

    /**
     * اطلاعات آموزش پرسنل (برای پروفایل)
     */
    public function getEmployeeTraining(int $userId): array
    {
        return EmployeeTraining::where('user_id', $userId)
            ->orderByDesc('synced_at')
            ->get()
            ->map(function ($t) {
                return [
                    'course_code'       => $t->course_code,
                    'course_title'      => $t->course_title,
                    'deputy'            => $t->deputy,
                    'management'        => $t->management,
                    'post_title'        => $t->post_title,
                    'session_duration'  => $t->session_duration,
                    'session_hours'     => round($t->session_hours, 2),
                    'performance_hours' => (float) $t->performance_hours,
                    'status_title'      => $t->status_title,
                    'status_severity'   => $t->status_severity,
                ];
            })
            ->toArray();
    }




    /**
     * اطلاعات تردد کارمند (خلاصه + جزئیات روزانه)
     */
    public function getEmployeeAttendance(int $userId, ?string $month = null): array
    {
        $user = User::find($userId);

        if (!$user || empty($user->personnel_code)) {
            return [
                'error' => 'کارمند یافت نشد یا کد پرسنلی ندارد.',
            ];
        }

        try {
            $attendanceService = app(\Modules\Attendance\App\Services\AttendanceService::class);

            // خلاصه + جزئیات روزانه
            $data = $attendanceService->getDailyAttendance(
                $user->personnel_code,
                $month ?: ''
            );

            // لیست ماه‌های موجود
            $availableMonths = $attendanceService->getAvailableMonths($user->personnel_code);

            return [
                'summary'          => $data['summary'] ?? null,
                'daily'            => $data['daily'] ?? [],
                'available_months' => $availableMonths,
                'selected_month'   => $month ?: ($data['summary']['monthKey'] ?? null),
            ];
        } catch (\Exception $e) {
            Log::error("getEmployeeAttendance({$userId}) failed: {$e->getMessage()}");
            return [
                'error' => 'خطا در دریافت اطلاعات تردد: ' . $e->getMessage(),
            ];
        }
    }

}
