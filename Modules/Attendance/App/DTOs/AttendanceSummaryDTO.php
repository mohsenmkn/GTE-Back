<?php

// Modules/Attendance/App/DTOs/AttendanceSummaryDTO.php

namespace Modules\Attendance\App\DTOs;

class AttendanceSummaryDTO
{
    public function __construct(
        public readonly string $personnelCode,
        public readonly string $employeeName,
        public readonly string $monthTitle,
        public readonly string $monthKey,

        // آمار روزها
        public readonly int $totalDays,
        public readonly int $workDays,
        public readonly int $presenceDays,
        public readonly int $absenceDays,
        public readonly int $leaveDays,
        public readonly int $missionDays,
        public readonly int $holidayDays,
        public readonly int $restDays,

        // زمان‌ها (به دقیقه)
        public readonly int $totalOvertimeMinutes,
        public readonly int $totalShortageMinutes,
        public readonly int $totalLateMinutes,
        public readonly int $totalEarlyLeaveMinutes,
        public readonly int $totalLeaveMinutes,
        public readonly int $totalMissionMinutes,

        // درصد حضور
        public readonly float $attendanceRate
    ) {}

    /**
     * تبدیل به آرایه برای JSON
     */
    public function toArray(): array
    {
        return [
            // ── فیلدهای flat (برای فرانت جدید — تب تردد پروفایل) ──
            'personnel_code'  => $this->personnelCode,
            'employee_name'   => $this->employeeName,
            'month_title'     => $this->monthTitle,
            'month_key'       => $this->monthKey,
            'total_days'      => $this->totalDays,
            'work_days'       => $this->workDays,
            'presence_days'   => $this->presenceDays,
            'absence_days'    => $this->absenceDays,
            'leave_days'      => $this->leaveDays,
            'mission_days'    => $this->missionDays,
            'holiday_days'    => $this->holidayDays,
            'rest_days'       => $this->restDays,
            'attendance_rate' => round($this->attendanceRate, 2),

            'total_overtime_minutes'      => $this->totalOvertimeMinutes,
            'total_shortage_minutes'      => $this->totalShortageMinutes,
            'total_late_minutes'          => $this->totalLateMinutes,
            'total_early_leave_minutes'   => $this->totalEarlyLeaveMinutes,
            'total_leave_minutes'         => $this->totalLeaveMinutes,
            'total_mission_minutes'       => $this->totalMissionMinutes,

            'overtime_formatted'    => $this->formatMinutes($this->totalOvertimeMinutes),
            'shortage_formatted'    => $this->formatMinutes($this->totalShortageMinutes),
            'late_formatted'        => $this->formatMinutes($this->totalLateMinutes),
            'early_leave_formatted' => $this->formatMinutes($this->totalEarlyLeaveMinutes),
            'leave_formatted'       => $this->formatMinutes($this->totalLeaveMinutes),
            'mission_formatted'     => $this->formatMinutes($this->totalMissionMinutes),

            // ── ساختار nested (برای فرانت قدیمی — داشبورد و مدیریت تردد) ──
            'days' => [
                'total'    => $this->totalDays,
                'work'     => $this->workDays,
                'presence' => $this->presenceDays,
                'absence'  => $this->absenceDays,
                'leave'    => $this->leaveDays,
                'mission'  => $this->missionDays,
                'holiday'  => $this->holidayDays,
                'rest'     => $this->restDays,
            ],
            'time' => [
                'overtime_formatted'    => $this->formatMinutes($this->totalOvertimeMinutes),
                'shortage_formatted'    => $this->formatMinutes($this->totalShortageMinutes),
                'late_formatted'        => $this->formatMinutes($this->totalLateMinutes),
                'early_leave_formatted' => $this->formatMinutes($this->totalEarlyLeaveMinutes),
                'leave_formatted'       => $this->formatMinutes($this->totalLeaveMinutes),
                'mission_formatted'     => $this->formatMinutes($this->totalMissionMinutes),
                'overtime_minutes'      => $this->totalOvertimeMinutes,
                'shortage_minutes'      => $this->totalShortageMinutes,
            ],
        ];
    }

    /**
     * تبدیل دقیقه به فرمت HH:MM
     */
    private function formatMinutes(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $mins  = $minutes % 60;
        return sprintf('%02d:%02d', $hours, $mins);
    }

}
