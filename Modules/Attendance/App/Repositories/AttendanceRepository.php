<?php

// Modules/Attendance/App/Repositories/AttendanceRepository.php

namespace Modules\Attendance\App\Repositories;

use Illuminate\Support\Facades\DB;

class AttendanceRepository
{
    private string $connection = 'kasra';

    /**
     * پیدا کردن PersonID از روی کد پرسنلی
     */
    public function findPersonIdByCode(string $personnelCode): ?int
    {
        return DB::connection($this->connection)->selectOne("
            SELECT ID
            FROM [framework].[Prs].[Person]
            WHERE CodeMask = ?
        ", [$personnelCode])->ID ?? null;
    }

    /**
     * 🔑 دریافت لیست تمام پرسنل
     */
    public function getAllEmployees(string $search = ''): array
    {
        $sql = "
            SELECT ID, DisplayName, CodeMask
            FROM [framework].[Prs].[Person]
        ";

        $params = [];

        if ($search) {
            $sql .= " WHERE CodeMask LIKE ? OR DisplayName LIKE ? ";
            $params = ["%{$search}%", "%{$search}%"];
        }

        $sql .= " ORDER BY CodeMask ASC";

        return DB::connection($this->connection)->select($sql, $params);
    }



    /**
     * دریافت نام کارمند
     */
    public function getEmployeeName(int $personId): ?string
    {
        return DB::connection($this->connection)->selectOne("
            SELECT DisplayName
            FROM [framework].[Prs].[Person]
            WHERE ID = ?
        ", [$personId])->DisplayName ?? null;
    }

    /**
     * دریافت اطلاعات روزانه تردد
     */
    public function getDailyAttendance(int $personId, string $currentMonth, string $prevMonth): array
    {
        $sql = "
            SET NOCOUNT ON;

            DECLARE @CurrentMonth NVARCHAR(7) = ?;
            DECLARE @PrevMonth NVARCHAR(7) = ?;
            DECLARE @PersonId INT = ?;

            DECLARE @Months TABLE
            (
                MonthKey NVARCHAR(7),
                MonthTitle NVARCHAR(50),
                SortOrder INT
            );

            INSERT INTO @Months VALUES
                (@CurrentMonth, N'ماه جاری', 1),
                (@PrevMonth, N'ماه قبل', 2);

            ;WITH DailyCalc AS
            (
                SELECT
                    D.personelid,
                    D.date,
                    LEFT(D.date, 7) AS MonthKey,

                    MAX(CASE WHEN D.Code = '10051' THEN D.Value END) AS RequiredMinutes,
                    MAX(CASE WHEN D.Code = '11251' THEN D.Value END) AS TotalPresenceMinutes,
                    MAX(CASE WHEN D.Code = '10101' THEN D.Value END) AS NormalPresenceMinutes,
                    MAX(CASE WHEN D.Code = '11101' THEN D.Value END) AS NormalOvertimeMinutes,

                    MAX(CASE WHEN D.Code = '14105' THEN D.Value END) AS HolidayCode14105,
                    MAX(CASE WHEN D.Code = '14112' THEN D.Value END) AS HolidayCode14112,
                    MAX(CASE WHEN D.Code = '14255' THEN D.Value END) AS HolidayCode14255,
                    MAX(CASE WHEN D.Code = '14268' THEN D.Value END) AS HolidayCode14268,
                    MAX(CASE WHEN D.Code = '14269' THEN D.Value END) AS HolidayCode14269,
                    MAX(CASE WHEN D.Code = '14308' THEN D.Value END) AS HolidayCode14308,

                    MAX(CASE WHEN D.Code = '11047' THEN D.Value END) AS DailyShortageMinutes,
                    MAX(CASE WHEN D.Code = '10102' THEN D.Value END) AS HourlyShortageMinutes,
                    MAX(CASE WHEN D.Code = '10150' THEN D.Value END) AS LateMinutes,
                    MAX(CASE WHEN D.Code = '10151' THEN D.Value END) AS EarlyLeaveMinutes,
                    MAX(CASE WHEN D.Code = '11001' THEN D.Value END) AS HourlyLeaveMinutes,
                    MAX(CASE WHEN D.Code = '11002' THEN D.Value END) AS DailyLeaveMinutes,
                    MAX(CASE WHEN D.Code = '14284' THEN D.Value END) AS DailyLeaveCount,
                    MAX(CASE WHEN D.Code = '11021' THEN D.Value END) AS HourlyMissionMinutes,
                    MAX(CASE WHEN D.Code = '14303' THEN D.Value END) AS DailyMissionMinutes,
                    MAX(CASE WHEN D.Code = '14136' THEN D.Value END) AS AbsenceCount,
                    MAX(CASE WHEN D.Code = '14033' THEN 1 END) AS IsRestDay,
                    MAX(CASE WHEN D.Code = '13097' THEN 1 END) AS IsHoliday
                FROM [framework].[Att].[DailyResult] AS D
                WHERE D.personelid = @PersonId
                  AND LEFT(D.date, 7) IN (SELECT MonthKey FROM @Months)
                GROUP BY D.personelid, D.date, LEFT(D.date, 7)
            ),
            DailyCalc2 AS
            (
                SELECT
                    DC.*,
                    CASE
                        WHEN DC.HolidayCode14269 IS NOT NULL THEN DC.HolidayCode14269
                        WHEN DC.HolidayCode14268 IS NOT NULL THEN DC.HolidayCode14268
                        WHEN DC.HolidayCode14308 IS NOT NULL THEN DC.HolidayCode14308
                        WHEN DC.HolidayCode14255 IS NOT NULL THEN DC.HolidayCode14255
                        WHEN DC.HolidayCode14112 IS NOT NULL THEN DC.HolidayCode14112
                        WHEN DC.HolidayCode14105 IS NOT NULL THEN DC.HolidayCode14105
                        ELSE 0
                    END AS HolidayOvertimeMinutes,
                    ISNULL(DC.HourlyLeaveMinutes, 0) + ISNULL(DC.DailyLeaveMinutes, 0) AS LeaveMinutes,
                    ISNULL(DC.HourlyMissionMinutes, 0) + ISNULL(DC.DailyMissionMinutes, 0) AS MissionMinutes,
                    ISNULL(DC.DailyShortageMinutes, 0) + ISNULL(DC.HourlyShortageMinutes, 0) AS TotalShortageMinutes
                FROM DailyCalc AS DC
            )
            SELECT
                M.MonthTitle,
                M.MonthKey,
                DC.date,
                DC.TotalPresenceMinutes,
                DC.RequiredMinutes,
                DC.NormalOvertimeMinutes,
                DC.HolidayOvertimeMinutes,
                DC.LeaveMinutes,
                DC.MissionMinutes,
                DC.TotalShortageMinutes,
                DC.LateMinutes,
                DC.EarlyLeaveMinutes,
                DC.AbsenceCount,
                DC.DailyLeaveCount,
                DC.IsHoliday,
                DC.IsRestDay,

                CASE
                    WHEN ISNULL(DC.AbsenceCount, 0) > 0 THEN N'absence'
                    WHEN ISNULL(DC.DailyLeaveCount, 0) > 0 OR ISNULL(DC.DailyLeaveMinutes, 0) > 0 THEN N'leave'
                    WHEN ISNULL(DC.DailyMissionMinutes, 0) > 0 THEN N'mission'
                    WHEN (ISNULL(DC.IsHoliday, 0) = 1 OR ISNULL(DC.IsRestDay, 0) = 1)
                     AND ISNULL(DC.TotalPresenceMinutes, 0) > 0 THEN N'holiday_presence'
                    WHEN ISNULL(DC.IsHoliday, 0) = 1 THEN N'holiday'
                    WHEN ISNULL(DC.IsRestDay, 0) = 1 THEN N'rest'
                    WHEN ISNULL(DC.TotalPresenceMinutes, 0) > 0 THEN N'presence'
                    ELSE N'no_record'
                END AS StatusType
            FROM DailyCalc2 AS DC
            INNER JOIN @Months AS M ON M.MonthKey = LEFT(DC.date, 7)
            ORDER BY DC.date DESC
        ";

        return DB::connection($this->connection)->select($sql, [$currentMonth, $prevMonth, $personId]);
    }


    public function getAvailableMonths(int $personId): array
    {
        return DB::connection($this->connection)->select("
        SELECT DISTINCT LEFT(date, 7) AS MonthKey
        FROM [framework].[Att].[DailyResult]
        WHERE personelid = ?
        ORDER BY MonthKey DESC
    ", [$personId]);
    }

    /**
     * دریافت پانچ‌های ورود/خروج یک کارمند برای یک ماه
     */
    public function getPunches(int $personId, string $month): array
    {
        return DB::connection($this->connection)->select("
        SELECT
            Date,
            Time,
            CardKhanNo
        FROM [framework].[Att].[Attendance]
        WHERE PersonelID = ?
          AND Date LIKE ?
          AND ISNULL(Deleted, 0) = 0
        ORDER BY Date ASC, Time ASC
    ", [$personId, $month . '%']);
    }

    /**
     * دریافت آخرین تردد کارمند
     *
     * منطق:
     * تعداد پانچ زوج   => ورود + خروج
     * تعداد پانچ فرد   => ورود بدون خروج
     */
    public function getLatestAttendance(int $personId): ?array
    {
        $rows = DB::connection($this->connection)->select("
        SELECT TOP 20
            Date,
            Time,
            CardKhanNo
        FROM [framework].[Att].[Attendance]
        WHERE PersonelID = ?
          AND ISNULL(Deleted, 0) = 0
        ORDER BY Date DESC, Time DESC
    ", [$personId]);

        if (empty($rows)) {
            return null;
        }

        // آخرین تاریخ دارای تردد
        $latestDate = $rows[0]->Date;

        // فقط پانچ‌های همان روز
        $dayPunches = collect($rows)
            ->filter(fn($row) => $row->Date === $latestDate)
            ->sortBy('Time')
            ->values();

        $count = $dayPunches->count();

        if ($count === 0) {
            return null;
        }

        $first = $dayPunches->first();
        $last = $dayPunches->last();

        $firstTime = $this->convertMinutesToTime((int) $first->Time);

        // تعداد زوج = خروج داریم
        $hasExit = $count % 2 === 0;

        $lastTime = $hasExit
            ? $this->convertMinutesToTime((int) $last->Time)
            : null;

        return [
            'date' => $latestDate,

            'first_time' => $firstTime,

            'last_time' => $lastTime,

            'punch_count' => $count,

            'has_entry' => true,

            'has_exit' => $hasExit,

            'status' => $hasExit
                ? 'completed'
                : 'inside',

            'last_punch_time' => $this->convertMinutesToTime(
                (int) $last->Time
            ),
        ];
    }

    private function convertMinutesToTime(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $mins = $minutes % 60;

        return sprintf('%02d:%02d', $hours, $mins);
    }

}
