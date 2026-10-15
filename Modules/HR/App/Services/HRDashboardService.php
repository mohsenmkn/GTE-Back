<?php

namespace Modules\HR\App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Modules\Auth\App\Models\User;
use Modules\HR\App\Models\EmployeePosition;
use Modules\HR\App\Models\EmployeeStatuteHistory;
use Modules\HR\App\Models\EmployeeTraining;
use Modules\HR\App\Models\HRSyncLog;
use Modules\HR\App\Models\OrganizationalPosition;
use Modules\HR\App\Models\OrganizationalUnit;
use Modules\Assessment\App\Models\Assessment;
use Modules\Assessment\App\Models\AssessmentAction;
use Modules\Assessment\App\Models\AssessmentGap;

class HRDashboardService
{
    /**
     * داشبورد مدیریتی منابع انسانی
     */
    public function getOverview(): array
    {
        return [
            'kpis'                    => $this->getStrategicKpis(),
            'workforce'               => $this->getWorkforceAnalysis(),
            'organization'            => $this->getOrganizationAnalysis(),
            'training'                => $this->getTrainingAnalytics(),
            'assessment'              => $this->getAssessmentAnalytics(),
            'charts'                  => [
                'employees_by_unit'   => $this->getEmployeesByUnit(),
                'workforce_composition' => $this->getWorkforceComposition(),
                'position_coverage'   => $this->getPositionCoverageByUnit(),
                'training_trend'      => $this->getTrainingTrend(),
                'tenure_distribution' => $this->getTenureDistribution(),
                'skill_gaps'          => $this->getSkillGapSeverity(),
            ],
            'alerts'                  => [
                'critical_vacancies'  => $this->getCriticalVacancies(),
                'overdue_actions'     => $this->getOverdueActions(),
                'recent_changes'      => $this->getRecentStatuteChanges(),
                'unassigned'          => $this->getUnassignedEmployees(),
            ],
            'sync_health'             => $this->getSyncHealth(),
        ];
    }

    /* ═══════════════════════════════════════════════
     *  ۱. شاخص‌های کلیدی استراتژیک
     * ═══════════════════════════════════════════════ */

    protected function getStrategicKpis(): array
    {
        // ── نیروی کار فعال ──
        $activeWorkforce = User::where('is_active', true)
            ->where('employee_type', '!=', 'contractor')
            ->count();

        $contractors = User::where('is_active', true)
            ->where('employee_type', 'contractor')
            ->count();

        $totalActive = $activeWorkforce + $contractors;

        // ── پوشش ساختار سازمانی ──
        $totalPositions = OrganizationalPosition::where('is_active', true)->count();
        $filledPositions = OrganizationalPosition::where('is_active', true)
            ->has('employees')
            ->count();
        $vacantPositions = $totalPositions - $filledPositions;
        $coverageRate = $totalPositions > 0
            ? round(($filledPositions / $totalPositions) * 100, 1)
            : 0;

        // ── سرانه آموزش ──
        $trainingStats = EmployeeTraining::select(
            DB::raw('COUNT(DISTINCT user_id) as trained_users'),
            DB::raw('SUM(performance_hours) as total_hours'),
            DB::raw('COUNT(*) as total_records')
        )
            ->first();

        $avgTrainingPerUser = $trainingStats->trained_users > 0
            ? round($trainingStats->total_hours / $trainingStats->trained_users, 1)
            : 0;

        // ── ارزیابی عملکرد ──
        $totalAssessments = Assessment::count();
        $completedAssessments = Assessment::whereIn('status', ['submitted', 'approved'])->count();
        $assessmentCompletionRate = $totalAssessments > 0
            ? round(($completedAssessments / $totalAssessments) * 100, 1)
            : 0;

        // ── اقدامات معوق ──
        $overdueActions = AssessmentAction::where('status', 'not in', ['completed', 'cancelled'])
            ->where('due_date', '<', now())
            ->count();

        $criticalGaps = AssessmentGap::where('status', '!=', 'closed')
            ->where('gap', '>', 0)
            ->whereHas('question', fn($q) => $q->where('risk_level', '>=', 4))
            ->count();

        return [
            'total_active_workforce'   => $totalActive,
            'official_personnel'       => $activeWorkforce,
            'contractors'              => $contractors,
            'position_coverage_rate'   => $coverageRate,
            'filled_positions'         => $filledPositions,
            'vacant_positions'         => $vacantPositions,
            'total_positions'          => $totalPositions,
            'avg_training_per_user'    => $avgTrainingPerUser,
            'trained_users'            => (int) $trainingStats->trained_users,
            'total_training_hours'     => round((float) $trainingStats->total_hours, 1),
            'assessment_completion'    => $assessmentCompletionRate,
            'completed_assessments'    => $completedAssessments,
            'total_assessments'        => $totalAssessments,
            'overdue_actions'          => $overdueActions,
            'critical_skill_gaps'      => $criticalGaps,
        ];
    }

    /* ═══════════════════════════════════════════════
     *  ۲. تحلیل نیروی کار
     * ═══════════════════════════════════════════════ */

    protected function getWorkforceAnalysis(): array
    {
        // تفکیک نوع استخدام
        $byType = User::where('is_active', true)
            ->select('employee_type', DB::raw('COUNT(*) as count'))
            ->groupBy('employee_type')
            ->pluck('count', 'employee_type');

        // تفکیک وضعیت فعال/غیرفعال
        $active = User::where('is_active', true)->count();
        $inactive = User::where('is_active', false)->count();

        // دارای سمت و بدون سمت
        $withPosition = User::where('is_active', true)->has('employeePosition')->count();
        $withoutPosition = User::where('is_active', true)->doesntHave('employeePosition')->count();

        return [
            'by_type' => [
                'official'     => (int) ($byType->get(null, 0) + $byType->get('official', 0)),
                'contractor'   => (int) $byType->get('contractor', 0),
            ],
            'by_status' => [
                'active'   => $active,
                'inactive' => $inactive,
            ],
            'by_position' => [
                'assigned'   => $withPosition,
                'unassigned' => $withoutPosition,
            ],
        ];
    }

    /* ═══════════════════════════════════════════════
     *  ۳. تحلیل ساختار سازمانی
     * ═══════════════════════════════════════════════ */

    protected function getOrganizationAnalysis(): array
    {
        $unitsTotal  = OrganizationalUnit::count();
        $unitsActive = OrganizationalUnit::where('is_active', true)->count();

        $positionsTotal  = OrganizationalPosition::count();
        $positionsActive = OrganizationalPosition::where('is_active', true)->count();
        $customPositions = OrganizationalPosition::where('is_custom', true)->count();

        // عمق سازمان
        $maxDepth = (int) OrganizationalUnit::where('is_active', true)->max('level');

        // میانگین دهانه مدیریت (تعداد پرسنل به ازای هر سمت فعال)
        $filledPositions = OrganizationalPosition::where('is_active', true)->has('employees')->count();
        $totalAssigned = EmployeePosition::count();
        $avgSpan = $filledPositions > 0 ? round($totalAssigned / $filledPositions, 1) : 0;

        return [
            'units' => [
                'total'  => $unitsTotal,
                'active' => $unitsActive,
                'custom' => OrganizationalUnit::where('is_custom', true)->count(),
            ],
            'positions' => [
                'total'  => $positionsTotal,
                'active' => $positionsActive,
                'custom' => $customPositions,
            ],
            'max_depth'          => $maxDepth,
            'avg_span_of_control'=> $avgSpan,
        ];
    }

    /* ═══════════════════════════════════════════════
     *  ۴. تحلیل آموزشی
     * ═══════════════════════════════════════════════ */

    protected function getTrainingAnalytics(): array
    {
        $stats = EmployeeTraining::select(
            DB::raw('COUNT(*) as total_courses'),
            DB::raw('COUNT(DISTINCT user_id) as trained_users'),
            DB::raw('SUM(performance_hours) as total_performance_hours')
        )
            ->first();

        // وضعیت دوره‌ها بر اساس منطق مدل
        $completed = EmployeeTraining::whereRaw('performance_hours >= (
            COALESCE(
                CAST(SUBSTRING_INDEX(session_duration, ":", 1) AS UNSIGNED) * 3600 +
                CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(session_duration, ":", 2), ":", -1) AS UNSIGNED) * 60 +
                CAST(SUBSTRING_INDEX(session_duration, ":", -1) AS UNSIGNED)
            , 0) / 3600
        )')->where('session_duration', '!=', '')->count();

        $inProgress = EmployeeTraining::where('performance_hours', '>', 0)
            ->whereNotIn('id', function ($q) {
                $q->select('id')->from('employee_trainings')
                    ->whereRaw('performance_hours >= (
                    COALESCE(
                        CAST(SUBSTRING_INDEX(session_duration, ":", 1) AS UNSIGNED) * 3600 +
                        CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(session_duration, ":", 2), ":", -1) AS UNSIGNED) * 60 +
                        CAST(SUBSTRING_INDEX(session_duration, ":", -1) AS UNSIGNED)
                    , 0) / 3600
                  )')
                    ->where('session_duration', '!=', '');
            })->count();

        $registered = $stats->total_courses - $completed - $inProgress;

        return [
            'total_courses'            => (int) $stats->total_courses,
            'trained_users'            => (int) $stats->trained_users,
            'total_performance_hours'  => round((float) $stats->total_performance_hours, 1),
            'avg_hours_per_trained'    => $stats->trained_users > 0
                ? round($stats->total_performance_hours / $stats->trained_users, 1)
                : 0,
            'completed'                => max(0, $completed),
            'in_progress'              => max(0, $inProgress),
            'registered'               => max(0, $registered),
            'completion_rate'          => $stats->total_courses > 0
                ? round(($completed / $stats->total_courses) * 100, 1)
                : 0,
        ];
    }

    /* ═══════════════════════════════════════════════
     *  ۵. تحلیل ارزیابی عملکرد
     * ═══════════════════════════════════════════════ */

    protected function getAssessmentAnalytics(): array
    {
        $byStatus = Assessment::select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        // گپ‌های باز بر اساس شدت
        $gaps = AssessmentGap::where('status', '!=', 'closed')
            ->where('gap', '>', 0)
            ->join('assessment_questions', 'assessment_questions.id', '=', 'assessment_gaps.question_id')
            ->select(
                DB::raw('CASE
                    WHEN assessment_questions.risk_level >= 4 AND assessment_gaps.gap >= 1 THEN "critical"
                    WHEN assessment_gaps.gap >= 2 THEN "major"
                    ELSE "minor"
                END as severity'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('severity')
            ->pluck('count', 'severity');

        // اقدامات بر اساس وضعیت
        $actions = AssessmentAction::select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        $overdueCount = AssessmentAction::where('status', 'not in', ['completed', 'cancelled'])
            ->where('due_date', '<', now())
            ->count();

        return [
            'assessments_by_status' => [
                'draft'     => (int) $byStatus->get('draft', 0),
                'submitted' => (int) $byStatus->get('submitted', 0),
                'approved'  => (int) $byStatus->get('approved', 0),
                'rejected'  => (int) $byStatus->get('rejected', 0),
            ],
            'open_gaps' => [
                'critical' => (int) $gaps->get('critical', 0),
                'major'    => (int) $gaps->get('major', 0),
                'minor'    => (int) $gaps->get('minor', 0),
            ],
            'actions' => [
                'planned'     => (int) $actions->get('planned', 0),
                'in_progress' => (int) $actions->get('in_progress', 0),
                'completed'   => (int) $actions->get('completed', 0),
                'cancelled'   => (int) $actions->get('cancelled', 0),
                'overdue'     => $overdueCount,
            ],
        ];
    }

    /* ═══════════════════════════════════════════════
     *  ۶. نمودارها
     * ═══════════════════════════════════════════════ */

    protected function getEmployeesByUnit(): array
    {
        $data = EmployeePosition::query()
            ->join('organizational_units', 'organizational_units.id', '=', 'employee_positions.organizational_unit_id')
            ->join('users', 'users.id', '=', 'employee_positions.user_id')
            ->where('users.is_active', true)
            ->whereNotNull('employee_positions.organizational_unit_id')
            ->select(
                'organizational_units.title',
                DB::raw('COUNT(DISTINCT employee_positions.user_id) as count')
            )
            ->groupBy('organizational_units.id', 'organizational_units.title')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        return [
            'labels' => $data->pluck('title')->toArray(),
            'values' => $data->pluck('count')->toArray(),
        ];
    }

    protected function getWorkforceComposition(): array
    {
        $official   = User::where('is_active', true)->where(fn($q) => $q->whereNull('employee_type')->orWhere('employee_type', '!=', 'contractor'))->count();
        $contractor = User::where('is_active', true)->where('employee_type', 'contractor')->count();
        $inactive   = User::where('is_active', false)->count();

        return [
            'labels' => ['پرسنل رسمی', 'پیمانکار', 'غیرفعال'],
            'values' => [$official, $contractor, $inactive],
        ];
    }

    protected function getPositionCoverageByUnit(): array
    {
        // استفاده از EXISTS به جای withCount (سازگار با select سفارشی)
        $employeeExists = "EXISTS (
        SELECT 1 FROM employee_positions
        WHERE employee_positions.organizational_position_id = organizational_positions.id
    )";

        $data = OrganizationalPosition::query()
            ->join(
                'organizational_units',
                'organizational_units.id',
                '=',
                'organizational_positions.organizational_unit_id'
            )
            ->where('organizational_positions.is_active', true)
            ->select(
                'organizational_units.id',
                'organizational_units.title',
                DB::raw("SUM(CASE WHEN {$employeeExists} THEN 1 ELSE 0 END) as filled"),
                DB::raw("SUM(CASE WHEN NOT {$employeeExists} THEN 1 ELSE 0 END) as vacant")
            )
            ->groupBy('organizational_units.id', 'organizational_units.title')
            ->orderByDesc(DB::raw('filled + vacant'))
            ->limit(8)
            ->get();

        return [
            'labels' => $data->pluck('title')->toArray(),
            'filled' => $data->pluck('filled')->map(fn($v) => (int) $v)->toArray(),
            'vacant' => $data->pluck('vacant')->map(fn($v) => (int) $v)->toArray(),
        ];
    }

    protected function getTrainingTrend(): array
    {
        $labels = [];
        $values = [];
        $hours  = [];

        $persianMonths = [
            1 => 'فروردین', 2 => 'اردیبهشت', 3 => 'خرداد',
            4 => 'تیر', 5 => 'مرداد', 6 => 'شهریور',
            7 => 'مهر', 8 => 'آبان', 9 => 'آذر',
            10 => 'دی', 11 => 'بهمن', 12 => 'اسفند',
        ];

        // ۱۲ ماه اخیر شمسی از داده‌های موجود
        $rawData = EmployeeTraining::whereNotNull('start_year')
            ->whereNotNull('start_month')
            ->select(
                'start_year',
                'start_month',
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(performance_hours) as hours')
            )
            ->groupBy('start_year', 'start_month')
            ->orderBy('start_year')
            ->orderBy('start_month')
            ->get()
            ->keyBy(fn($r) => $r->start_year * 100 + $r->start_month);

        // ساخت ۱۲ ماه اخیر
        $currentJalali = $this->gregorianToJalali(now());
        $currentYear  = $currentJalali[0];
        $currentMonth = $currentJalali[1];

        for ($i = 11; $i >= 0; $i--) {
            $month = $currentMonth - $i;
            $year  = $currentYear;
            while ($month < 1) {
                $month += 12;
                $year--;
            }

            $key = $year * 100 + $month;
            $labels[] = $persianMonths[$month] . ' ' . $year;
            $values[] = (int) ($rawData->get($key)?->count ?? 0);
            $hours[]  = round((float) ($rawData->get($key)?->hours ?? 0), 1);
        }

        return [
            'labels' => $labels,
            'values' => $values,
            'hours'  => $hours,
        ];
    }

    protected function getTenureDistribution(): array
    {
        // محاسبه سابقه از اولین حکم هر کاربر
        $tenures = EmployeeStatuteHistory::select(
            'user_id',
            DB::raw('MIN(issue_date) as first_date')
        )
            ->whereNotNull('issue_date')
            ->groupBy('user_id')
            ->get()
            ->map(function ($row) {
                return now()->diffInYears(Carbon::parse($row->first_date));
            })
            ->filter(fn($years) => $years !== null && $years >= 0);

        $ranges = [
            'کمتر از ۱ سال'  => $tenures->filter(fn($y) => $y < 1)->count(),
            '۱ تا ۳ سال'     => $tenures->filter(fn($y) => $y >= 1 && $y < 3)->count(),
            '۳ تا ۵ سال'     => $tenures->filter(fn($y) => $y >= 3 && $y < 5)->count(),
            '۵ تا ۱۰ سال'    => $tenures->filter(fn($y) => $y >= 5 && $y < 10)->count(),
            '۱۰ تا ۲۰ سال'   => $tenures->filter(fn($y) => $y >= 10 && $y < 20)->count(),
            'بیش از ۲۰ سال'  => $tenures->filter(fn($y) => $y >= 20)->count(),
        ];

        return [
            'labels' => array_keys($ranges),
            'values' => array_values($ranges),
        ];
    }

    protected function getSkillGapSeverity(): array
    {
        $gaps = AssessmentGap::where('status', '!=', 'closed')
            ->where('gap', '>', 0)
            ->join('assessment_questions', 'assessment_questions.id', '=', 'assessment_gaps.question_id')
            ->select(
                DB::raw('CASE
                    WHEN assessment_questions.risk_level >= 4 AND assessment_gaps.gap >= 1 THEN "critical"
                    WHEN assessment_gaps.gap >= 2 THEN "major"
                    ELSE "minor"
                END as severity'),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(assessment_gaps.weighted_gap) as total_weighted')
            )
            ->groupBy('severity')
            ->pluck('count', 'severity');

        return [
            'labels' => ['بحرانی', 'عمده', 'جزئی'],
            'values' => [
                (int) $gaps->get('critical', 0),
                (int) $gaps->get('major', 0),
                (int) $gaps->get('minor', 0),
            ],
        ];
    }

    /* ═══════════════════════════════════════════════
     *  ۷. هشدارها و لیست‌های عملیاتی
     * ═══════════════════════════════════════════════ */

    protected function getCriticalVacancies(): array
    {
        return OrganizationalPosition::where('is_active', true)
            ->doesntHave('employees')
            ->with('unit:id,title')
            ->orderBy('sort_order')
            ->limit(6)
            ->get()
            ->map(fn($p) => [
                'id'         => $p->id,
                'title'      => $p->post_title,
                'unit'       => $p->unit?->title ?? '—',
                'is_custom'  => $p->is_custom,
                'job_title'  => $p->job_title,
            ])
            ->toArray();
    }

    protected function getOverdueActions(): array
    {
        return AssessmentAction::where('status', 'not in', ['completed', 'cancelled'])
            ->where('due_date', '<', now())
            ->with(['gap.assessment.employee:id,name', 'method:id,title'])
            ->orderBy('due_date')
            ->limit(5)
            ->get()
            ->map(fn($a) => [
                'id'            => $a->id,
                'title'         => $a->title,
                'due_date'      => $a->due_date?->format('Y/m/d'),
                'days_overdue'  => (int) now()->diffInDays($a->due_date, false),
                'employee_name' => $a->gap?->assessment?->employee?->name ?? '—',
                'method'        => $a->method?->title ?? '—',
            ])
            ->toArray();
    }

    protected function getRecentStatuteChanges(): array
    {
        return EmployeeStatuteHistory::query()
            ->with('user:id,name')
            ->whereNotNull('issue_date')
            ->orderByDesc('issue_date')
            ->limit(6)
            ->get()
            ->map(fn($h) => [
                'id'           => $h->id,
                'employee'     => $h->user?->name ?? '—',
                'post_title'   => $h->post_title ?? '—',
                'job_title'    => $h->job_title,
                'issue_date'   => $h->issue_date?->format('Y/m/d'),
                'statute_no'   => $h->statute_number,
            ])
            ->toArray();
    }

    protected function getUnassignedEmployees(): array
    {
        return User::where('is_active', true)
            ->where('employee_type', '!=', 'contractor')
            ->doesntHave('employeePosition')
            ->select('id', 'name', 'personnel_code')
            ->orderBy('name')
            ->limit(5)
            ->get()
            ->toArray();
    }

    /* ═══════════════════════════════════════════════
     *  ۸. سلامت همگام‌سازی
     * ═══════════════════════════════════════════════ */

    protected function getSyncHealth(): array
    {
        $todaySuccess = HRSyncLog::today()->success()->count();
        $todayFailed  = HRSyncLog::today()->failed()->count();

        $lastSync = HRSyncLog::orderByDesc('created_at')->first();

        // رکوردهای قدیمی (بیش از ۲۴ ساعت از آخرین سینک)
        $staleRecords = EmployeePosition::where(function ($q) {
            $q->whereNull('synced_at')
                ->orWhere('synced_at', '<', now()->subHours(24));
        })
            ->count();

        $failedRecords = EmployeePosition::where('sync_failed', true)->count();

        // آخرین سینک‌ها
        $recentLogs = HRSyncLog::with('user:id,name')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn($log) => [
                'id'           => $log->id,
                'type'         => $log->sync_type,
                'status'       => $log->status,
                'records'      => $log->records_synced,
                'duration_ms'  => $log->duration_ms,
                'trigger'      => $log->trigger_source,
                'user'         => $log->user?->name,
                'created_at'   => $log->created_at?->format('Y/m/d H:i'),
            ])
            ->toArray();

        return [
            'today_success'   => $todaySuccess,
            'today_failed'    => $todayFailed,
            'last_sync_at'    => $lastSync?->created_at?->toISOString(),
            'last_sync_status'=> $lastSync?->status,
            'stale_records'   => $staleRecords,
            'failed_records'  => $failedRecords,
            'recent_logs'     => $recentLogs,
        ];
    }

    /* ═══════════════════════════════════════════════
     *  Helper: تبدیل میلادی به شمسی
     * ═══════════════════════════════════════════════ */

    protected function gregorianToJalali(Carbon $date): array
    {
        if (class_exists(\Morilog\Jalali\Jalalian::class)) {
            $jalali = \Morilog\Jalali\Jalalian::fromCarbon($date);
            return [$jalali->getYear(), $jalali->getMonth(), $jalali->getDay()];
        }

        // Fallback ساده
        $gy = $date->year;
        $gm = $date->month;
        $gd = $date->day;

        $g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
        $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
        $days = 355666 + (365 * $gy) + ((int)(($gy2 + 3) / 4)) - ((int)(($gy2 + 99) / 100)) + ((int)(($gy2 + 399) / 400)) + $gd + $g_d_m[$gm - 1];
        $jy = -1595 + (33 * ((int)($days / 12053)));
        $days %= 12053;
        $jy += 4 * ((int)($days / 1461));
        $days %= 1461;
        if ($days > 365) {
            $jy += (int)(($days - 1) / 365);
            $days = ($days - 1) % 365;
        }
        $jm = ($days < 186) ? 1 + (int)($days / 31) : 7 + (int)(($days - 186) / 30);
        $jd = 1 + (($days < 186) ? ($days % 31) : (($days - 186) % 30));

        return [$jy, $jm, $jd];
    }
}
