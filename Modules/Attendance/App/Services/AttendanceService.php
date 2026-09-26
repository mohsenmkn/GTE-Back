<?php

// Modules/Attendance/App/Services/AttendanceService.php

namespace Modules\Attendance\App\Services;

use Modules\Attendance\App\Repositories\AttendanceRepository;
use Modules\Attendance\App\DTOs\AttendanceSummaryDTO;

class AttendanceService
{
    public function __construct(
        private AttendanceRepository $repository
    ) {}

    /**
     * دریافت خلاصه تردد برای یک کارمند
     */
    public function getAttendanceSummary(string $personnelCode): array
    {
        $personId = $this->repository->findPersonIdByCode($personnelCode);

        if (!$personId) {
            return [];
        }

        // محاسبه ماه جاری و ماه قبل (شمسی)
        $currentMonth = $this->getCurrentShamsiMonth();
        $prevMonth = $this->getPreviousShamsiMonth();

        $dailyData = $this->repository->getDailyAttendance($personId, $currentMonth, $prevMonth);
        $employeeName = $this->repository->getEmployeeName($personId);

        // گروه‌بندی بر اساس ماه
        $grouped = collect($dailyData)->groupBy('MonthKey');

        $summaries = [];
        foreach ($grouped as $monthKey => $days) {
            $summaries[] = $this->calculateSummary(
                $personnelCode,
                $employeeName,
                $days->first()->MonthTitle,
                $monthKey,
                $days->toArray()
            );
        }

        return $summaries;
    }

    /**
     * محاسبه خلاصه یک ماه
     */
    private function calculateSummary(
        string $personnelCode,
        string $employeeName,
        string $monthTitle,
        string $monthKey,
        array $days
    ): AttendanceSummaryDTO {
        $totalDays = count($days);
        $workDays = 0;
        $presenceDays = 0;
        $absenceDays = 0;
        $leaveDays = 0;
        $missionDays = 0;
        $holidayDays = 0;
        $restDays = 0;

        $totalOvertime = 0;
        $totalShortage = 0;
        $totalLate = 0;
        $totalEarlyLeave = 0;
        $totalLeave = 0;
        $totalMission = 0;

        foreach ($days as $day) {
            $status = $day->StatusType;

            switch ($status) {
                case 'presence':
                case 'holiday_presence':
                    $presenceDays++;
                    $workDays++;
                    break;
                case 'absence':
                    $absenceDays++;
                    $workDays++;
                    break;
                case 'leave':
                    $leaveDays++;
                    $workDays++;
                    break;
                case 'mission':
                    $missionDays++;
                    $workDays++;
                    break;
                case 'holiday':
                    $holidayDays++;
                    break;
                case 'rest':
                    $restDays++;
                    break;
            }

            $totalOvertime += ($day->NormalOvertimeMinutes ?? 0) + ($day->HolidayOvertimeMinutes ?? 0);
            $totalShortage += $day->TotalShortageMinutes ?? 0;
            $totalLate += $day->LateMinutes ?? 0;
            $totalEarlyLeave += $day->EarlyLeaveMinutes ?? 0;
            $totalLeave += $day->LeaveMinutes ?? 0;
            $totalMission += $day->MissionMinutes ?? 0;
        }

        // درصد حضور = روزهای حضور / روزهای کاری
        $attendanceRate = $workDays > 0 ? ($presenceDays / $workDays) * 100 : 0;

        return new AttendanceSummaryDTO(
            personnelCode: $personnelCode,
            employeeName: $employeeName,
            monthTitle: $monthTitle,
            monthKey: $monthKey,
            totalDays: $totalDays,
            workDays: $workDays,
            presenceDays: $presenceDays,
            absenceDays: $absenceDays,
            leaveDays: $leaveDays,
            missionDays: $missionDays,
            holidayDays: $holidayDays,
            restDays: $restDays,
            totalOvertimeMinutes: $totalOvertime,
            totalShortageMinutes: $totalShortage,
            totalLateMinutes: $totalLate,
            totalEarlyLeaveMinutes: $totalEarlyLeave,
            totalLeaveMinutes: $totalLeave,
            totalMissionMinutes: $totalMission,
            attendanceRate: $attendanceRate
        );
    }

    /**
     * دریافت ماه جاری شمسی (مثلاً 1405/05)
     */
    private function getCurrentShamsiMonth(): string
    {
        // استفاده از تاریخ سرور یا تبدیل
        // برای سادگی، از یک helper استفاده می‌کنیم
        return $this->getShamsiYearMonth(now());
    }

    /**
     * دریافت ماه قبل شمسی
     */
    private function getPreviousShamsiMonth(): string
    {
        // 🔑 استفاده از پکیج jalali
        if (class_exists(\Morilog\Jalali\Jalalian::class)) {
            $current = \Morilog\Jalali\Jalalian::now();
            $previous = $current->subMonths(1);
            return $previous->format('Y/m');
        }

        // 🔑 fallback: محاسبه دستی
        $current = $this->getCurrentShamsiMonth(); // مثلاً "1405/05"
        [$year, $month] = explode('/', $current);
        $year = (int) $year;
        $month = (int) $month;

        if ($month === 1) {
            return ($year - 1) . '/12';
        }

        return $year . '/' . str_pad($month - 1, 2, '0', STR_PAD_LEFT);
    }

    /**
     * تبدیل تاریخ میلادی به فرمت شمسی YYYY/MM
     */
    private function getShamsiYearMonth(\DateTimeInterface $date): string
    {
        // اگر پکیج heidarimoradi/jdf یا morilog/jalali دارید:
        if (function_exists('jdate')) {
            return jdate($date)->format('Y/m');
        }

        // fallback: استفاده از verta یا محاسبه دستی
        // برای حالا، مقدار ثابت برمی‌گردانیم - در production باید اصلاح شود
        return '1405/05';
    }


    /**
     * 🔑 دریافت لیست تمام پرسنل با خلاصه تردد ماه جاری
     */
    public function getAllEmployeesAttendance(string $search = '', string $month = ''): array
    {
        $currentMonth = $month ?: $this->getCurrentShamsiMonth();
        $prevMonth = $month ? $this->getPreviousMonth($month) : $this->getPreviousShamsiMonth();

        // دریافت لیست پرسنل از دیتابیس گستراب (یا کسرا)
        $employees = $this->repository->getAllEmployees($search);

        $result = [];
        foreach ($employees as $emp) {
            $summary = $this->calculateMonthSummary(
                $emp->CodeMask,
                $emp->DisplayName,
                $currentMonth,
                $prevMonth
            );

            if ($summary) {
                $result[] = [
                    'personnel_code' => $emp->CodeMask,
                    'person_id' => $emp->ID,
                    'employee_name' => $emp->DisplayName,
                    'summary' => $summary->toArray(),
                ];
            }
        }

        return [
            'data' => $result,
            'meta' => [
                'total' => count($result),
                'month' => $currentMonth,
                'search' => $search,
            ],
        ];
    }

    /**
     * دریافت جزئیات روزانه + خلاصه ماه + پانچ‌های ورود/خروج
     */
    public function getDailyAttendance(string $personnelCode, string $month = ''): array
    {
        $personId = $this->repository->findPersonIdByCode($personnelCode);
        if (!$personId) {
            return [];
        }

        $targetMonth = $month ?: $this->getCurrentShamsiMonth();
        $prevMonth = $this->getPreviousMonth($targetMonth);

        // ۱. دریافت داده‌های روزانه از DailyResult
        $dailyData = $this->repository->getDailyAttendance($personId, $targetMonth, $prevMonth);

        // ۲. ✅ دریافت پانچ‌های ورود/خروج از جدول Attendance
        $punches = $this->repository->getPunches($personId, $targetMonth);
        $punchesMap = $this->calculateDailyPunches($punches);

        // فیلتر فقط ماه مورد نظر
        $filteredData = collect($dailyData)
            ->filter(fn($d) => $d->MonthKey === $targetMonth)
            ->values()
            ->toArray();

        // محاسبه خلاصه
        $summary = $this->buildSummary(
            $personnelCode,
            $this->repository->getEmployeeName($personId) ?? '',
            'ماه انتخابی',
            $targetMonth,
            $filteredData
        );

        // تبدیل داده‌های روزانه با ادغام پانچ‌ها
        $dailyRows = collect($filteredData)->map(function ($day) use ($punchesMap) {
            $overtimeMinutes = ($day->NormalOvertimeMinutes ?? 0) + ($day->HolidayOvertimeMinutes ?? 0);

            // ✅ دریافت اطلاعات پانچ برای این روز
            $punchInfo = $punchesMap[$day->date] ?? [
                'first_time'  => '--:--',
                'last_time'   => '--:--',
                'punch_count' => 0,
            ];

            return [
                'date' => $day->date,
                'weekday' => $this->getWeekdayName($day->date),

                // ✅ ورود و خروج از پانچ‌ها (نه از فیلدهای ناموجود)
                'first_time'   => $punchInfo['first_time'],
                'last_time'    => $punchInfo['last_time'],
                'punch_count'  => $punchInfo['punch_count'],

                'status' => $this->getStatusPersian($day->StatusType),
                'required_minutes' => $day->RequiredMinutes ?? 0,
                'required_formatted' => $this->formatMinutes($day->RequiredMinutes ?? 0),
                'presence_minutes' => $day->TotalPresenceMinutes ?? 0,
                'presence_formatted' => $this->formatMinutes($day->TotalPresenceMinutes ?? 0),
                'overtime_minutes' => $overtimeMinutes,
                'overtime_formatted' => $this->formatMinutes($overtimeMinutes),
                'shortage_minutes' => $day->TotalShortageMinutes ?? 0,
                'shortage_formatted' => $this->formatMinutes($day->TotalShortageMinutes ?? 0),
                'late_minutes' => $day->LateMinutes ?? 0,
                'late_formatted' => $this->formatMinutes($day->LateMinutes ?? 0),
                'early_leave_minutes' => $day->EarlyLeaveMinutes ?? 0,
                'early_leave_formatted' => $this->formatMinutes($day->EarlyLeaveMinutes ?? 0),
                'leave_minutes' => $day->LeaveMinutes ?? 0,
                'leave_formatted' => $this->formatMinutes($day->LeaveMinutes ?? 0),
                'mission_minutes' => $day->MissionMinutes ?? 0,
                'mission_formatted' => $this->formatMinutes($day->MissionMinutes ?? 0),
                'absence_count' => $day->AbsenceCount ?? 0,
                'daily_leave_count' => $day->DailyLeaveCount ?? 0,
            ];
        })->toArray();

        return [
            'summary' => $summary->toArray(),
            'daily' => $dailyRows,
        ];
    }

    /**
     * محاسبه خلاصه یک ماه
     */
    private function calculateMonthSummary(
        string $personnelCode,
        string $employeeName,
        string $currentMonth,
        string $prevMonth
    ): ?AttendanceSummaryDTO {
        $personId = $this->repository->findPersonIdByCode($personnelCode);

        if (!$personId) {
            return null;
        }

        $dailyData = $this->repository->getDailyAttendance($personId, $currentMonth, $prevMonth);
        $currentMonthData = collect($dailyData)->filter(fn($d) => $d->MonthKey === $currentMonth)->values()->toArray();

        if (empty($currentMonthData)) {
            return null;
        }

        return $this->buildSummary($personnelCode, $employeeName, 'ماه جاری', $currentMonth, $currentMonthData);
    }

    /**
     * ساخت DTO خلاصه
     */
    private function buildSummary(
        string $personnelCode,
        string $employeeName,
        string $monthTitle,
        string $monthKey,
        array $days
    ): AttendanceSummaryDTO {
        $totalDays = count($days);
        $workDays = 0;
        $presenceDays = 0;
        $absenceDays = 0;
        $leaveDays = 0;
        $missionDays = 0;
        $holidayDays = 0;
        $restDays = 0;

        $totalOvertime = 0;
        $totalShortage = 0;
        $totalLate = 0;
        $totalEarlyLeave = 0;
        $totalLeave = 0;
        $totalMission = 0;

        foreach ($days as $day) {
            $status = $day->StatusType;

            switch ($status) {
                case 'presence':
                case 'holiday_presence':
                    $presenceDays++;
                    $workDays++;
                    break;
                case 'absence':
                    $absenceDays++;
                    $workDays++;
                    break;
                case 'leave':
                    $leaveDays++;
                    $workDays++;
                    break;
                case 'mission':
                    $missionDays++;
                    $workDays++;
                    break;
                case 'holiday':
                    $holidayDays++;
                    break;
                case 'rest':
                    $restDays++;
                    break;
            }

            $totalOvertime += ($day->NormalOvertimeMinutes ?? 0) + ($day->HolidayOvertimeMinutes ?? 0);
            $totalShortage += $day->TotalShortageMinutes ?? 0;
            $totalLate += $day->LateMinutes ?? 0;
            $totalEarlyLeave += $day->EarlyLeaveMinutes ?? 0;
            $totalLeave += $day->LeaveMinutes ?? 0;
            $totalMission += $day->MissionMinutes ?? 0;
        }

        $attendanceRate = $workDays > 0 ? ($presenceDays / $workDays) * 100 : 0;

        return new AttendanceSummaryDTO(
            personnelCode: $personnelCode,
            employeeName: $employeeName,
            monthTitle: $monthTitle,
            monthKey: $monthKey,
            totalDays: $totalDays,
            workDays: $workDays,
            presenceDays: $presenceDays,
            absenceDays: $absenceDays,
            leaveDays: $leaveDays,
            missionDays: $missionDays,
            holidayDays: $holidayDays,
            restDays: $restDays,
            totalOvertimeMinutes: $totalOvertime,
            totalShortageMinutes: $totalShortage,
            totalLateMinutes: $totalLate,
            totalEarlyLeaveMinutes: $totalEarlyLeave,
            totalLeaveMinutes: $totalLeave,
            totalMissionMinutes: $totalMission,
            attendanceRate: $attendanceRate
        );
    }

    // Helper methods
    private function formatMinutes(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $mins = $minutes % 60;
        return sprintf('%02d:%02d', $hours, $mins);
    }

    private function formatTime(?string $time): string
    {
        return $time ?: '--:--';
    }

    private function getStatusPersian(string $status): string
    {
        $map = [
            'presence' => 'حضور',
            'absence' => 'غیبت',
            'leave' => 'مرخصی',
            'mission' => 'مأموریت',
            'holiday' => 'تعطیل',
            'rest' => 'استراحت',
            'holiday_presence' => 'حضور در تعطیل',
            'no_record' => 'بدون ثبت',
        ];
        return $map[$status] ?? $status;
    }

    /**
     * 🔑 تبدیل تاریخ شمسی به روز هفته
     */
    private function getWeekdayName(string $shamsiDate): string
    {
        $parts = explode('/', $shamsiDate);
        if (count($parts) !== 3) return '';

        [$jy, $jm, $jd] = array_map('intval', $parts);

        // تبدیل به میلادی
        $miladi = $this->shamsiToMiladi($jy, $jm, $jd);

        if (!$miladi) return '';

        // گرفتن روز هفته از تاریخ میلادی
        $timestamp = strtotime($miladi);
        $dayOfWeek = date('w', $timestamp); // 0=Sunday, 1=Monday, ..., 6=Saturday

        $days = [
            0 => 'یکشنبه',
            1 => 'دوشنبه',
            2 => 'سه‌شنبه',
            3 => 'چهارشنبه',
            4 => 'پنجشنبه',
            5 => 'جمعه',
            6 => 'شنبه',
        ];

        return $days[$dayOfWeek] ?? '';
    }

    private function getPreviousMonth(string $month): string
    {
        [$year, $m] = explode('/', $month);
        $year = (int) $year;
        $m = (int) $m;

        if ($m === 1) {
            return ($year - 1) . '/12';
        }

        return $year . '/' . str_pad($m - 1, 2, '0', STR_PAD_LEFT);
    }



    /**
     *  دریافت لیست ماه‌های موجود برای یک کارمند
     */
    public function getAvailableMonths(string $personnelCode): array
    {
        $personId = $this->repository->findPersonIdByCode($personnelCode);

        if (!$personId) {
            return [];
        }

        // دریافت ماه‌هایی که داده دارند
        $months = $this->repository->getAvailableMonths($personId);

        return $months;
    }



    /**
     * 🔑 تبدیل تاریخ شمسی به میلادی
     */
    private function shamsiToMiladi(int $jy, int $jm, int $jd): string
    {
        $jy += 1595;
        $days = -355668 + (365 * $jy) + (intdiv($jy, 33) * 8) + (intdiv($jy % 33 + 3, 4)) + $jd + (($jm < 7) ? ($jm - 1) * 31 : (($jm - 1) * 30) + 6);

        $gy = 400 * (intdiv($days, 146097));
        $days %= 146097;

        if ($days > 36524) {
            $gy += 100 * (intdiv(--$days, 36524));
            $days %= 36524;
            if ($days >= 365) $days++;
        }

        $gy += 4 * (intdiv($days, 1461));
        $days %= 1461;

        if ($days > 365) {
            $gy += (intdiv($days - 1, 365));
            $days = ($days - 1) % 365;
        }

        $gd = $days + 1;
        $sal_a = [0, 31, (($gy % 4 === 0 && $gy % 100 !== 0) || ($gy % 400 === 0)) ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        $gm = 0;

        for ($gm = 0; $gm < 13 && $gd > $sal_a[$gm]; $gm++) {
            $gd -= $sal_a[$gm];
        }

        return sprintf('%04d-%02d-%02d', $gy, $gm, $gd);
    }

    /**
     * محاسبه ورود/خروج روزانه از پانچ‌ها
     *
     * منطق: پانچ‌ها به ترتیب زمان مرتب می‌شوند
     * - پانچ اول  = ورود اول  (first_time)
     * - پانچ آخر  = خروج آخر   (last_time)
     */
    private function calculateDailyPunches(array $punches): array
    {
        $grouped = collect($punches)->groupBy('Date');
        $result = [];

        foreach ($grouped as $date => $dayPunches) {
            $sorted = $dayPunches->sortBy('Time')->values();
            $count  = $sorted->count();

            // پانچ اول = ورود اول
            $firstTime = $count > 0
                ? $this->convertMinutesToTime((int) $sorted[0]->Time)
                : '--:--';

            // پانچ آخر = خروج آخر
            $lastTime = $count > 1
                ? $this->convertMinutesToTime((int) $sorted[$count - 1]->Time)
                : '--:--';

            $result[$date] = [
                'first_time'  => $firstTime,
                'last_time'   => $lastTime,
                'punch_count' => $count,
            ];
        }

        return $result;
    }

    /**
     * تبدیل دقیقه از نیمه‌شب به فرمت HH:MM
     * مثال: 380 → 06:20
     */
    private function convertMinutesToTime(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $mins  = $minutes % 60;
        return sprintf('%02d:%02d', $hours, $mins);
    }

    /**
     * دریافت آخرین ورود و خروج کارمند
     */
    public function getLatestAttendance(string $personnelCode): array
    {
        $personId = $this->repository->findPersonIdByCode($personnelCode);

        if (!$personId) {
            return [];
        }

        $attendance = $this->repository->getLatestAttendance($personId);

        if (!$attendance) {
            return [];
        }

        return [
            'date' => $attendance['date'],

            'first_time' => $attendance['first_time'],

            'last_time' => $attendance['last_time'] ?? '--:--',

            'last_punch_time' => $attendance['last_punch_time'],

            'punch_count' => $attendance['punch_count'],

            'has_entry' => $attendance['has_entry'],

            'has_exit' => $attendance['has_exit'],

            'status' => $attendance['status'],
        ];
    }

}
