<?php


namespace Modules\HR\App\Services;

use Illuminate\Support\Facades\Log;
use Modules\Auth\App\Models\User;
use Modules\HR\App\Models\EmployeeTraining;
use Modules\HR\App\Models\HRSyncLog;

class TrainingSyncService
{
    public function __construct(
        private TrainingSoapClient $soapClient
    )
    {
    }

    /**
     * sync آموزش‌های یک کاربر
     *
     * @param bool $fullSync true = از 1380 تا امروز، false = 6 ماه اخیر
     */
    /**
     * sync آموزش‌های یک کاربر
     *
     * @param bool $fullSync true = از 1380، false = 6 ماه اخیر
     * @param string|null $fromDateOverride ✅ بازه دلخواه (مثل 13920101)
     */
    public function syncUser(User $user, bool $fullSync = false, ?string $fromDateOverride = null): int
    {
        if (empty($user->national_code)) {
            return 0;
        }

        $startTime = microtime(true);

        try {
            [$fromDate, $toDate] = $this->calculateDateRange($fullSync);

            // ✅ بازه دلخواه برای sync سراسری
            if ($fromDateOverride) {
                $fromDate = $fromDateOverride;
            }

            $rawXml = $this->soapClient->fetchRaw(
                $user->national_code,
                $fromDate,
                $toDate
            );

            if (!$rawXml) {
                $this->logSync($user->id, 'failed', 0, 'Empty SOAP response', $startTime);
                return 0;
            }

            $records = $this->soapClient->parseResponse($rawXml);

            if (empty($records)) {
                $this->logSync($user->id, 'success', 0, null, $startTime);
                return 0;
            }

            $syncedCount = 0;

            foreach ($records as $record) {
                if (empty($record['course_code'])) continue;

                $externalId = $user->national_code . '_' . $record['course_code'];

                try {
                    EmployeeTraining::updateOrCreate(
                        ['external_id' => $externalId],
                        [
                            'user_id'           => $user->id,
                            'national_code'     => $user->national_code,
                            'first_name'        => $record['first_name'] ?? null,
                            'last_name'         => $record['last_name'] ?? null,
                            'deputy'            => $this->cleanField($record['deputy'] ?? null),
                            'management'        => $this->cleanField($record['management'] ?? null),
                            'post_title'        => $record['post_title'] ?? null,
                            'course_code'       => $record['course_code'],
                            'course_title'      => $record['course_title'] ?? 'بدون عنوان',
                            'session_duration'  => $record['session_duration'] ?? null,
                            'performance_hours' => (float) ($record['performance_hours'] ?? 0),
                            'soap_raw_data'     => $record,
                            'synced_at'         => now(),
                        ]
                    );
                    $syncedCount++;
                } catch (\Exception $e) {
                    Log::error("syncUser: save failed", [
                        'course_code' => $record['course_code'] ?? '?',
                        'error'       => $e->getMessage(),
                    ]);
                }
            }

            $this->logSync($user->id, 'success', $syncedCount, null, $startTime);
            return $syncedCount;
        } catch (\Exception $e) {
            Log::error("syncUser({$user->id}) failed: {$e->getMessage()}");
            $this->logSync($user->id, 'failed', 0, $e->getMessage(), $startTime);
            return 0;
        }
    }

    /**
     * sync آموزش‌های همه کاربران
     */
    /**
     * ✅ sync آموزش همه کاربران با بازه دلخواه
     *
     * @param string $fromDate تاریخ شروع (YYYYMMDD شمسی)
     * @param callable|null $progress callback برای progress bar
     * @param int $delayMs فاصله بین درخواست‌ها (جلوگیری از بلاک WAF)
     */
    public function syncAllFrom(string $fromDate, ?callable $progress = null, int $delayMs = 200): array
    {
        $stats = [
            'total'          => 0,
            'success'        => 0,
            'failed'         => 0,
            'records_synced' => 0,
        ];

        User::whereNotNull('national_code')
            ->where('national_code', '!=', '')
            ->chunkById(100, function ($users) use (&$stats, $fromDate, $progress, $delayMs) {
                foreach ($users as $user) {
                    $stats['total']++;

                    try {
                        $count = $this->syncUser($user, false, $fromDate);
                        $stats['success']++;
                        $stats['records_synced'] += $count;
                    } catch (\Throwable $e) {
                        $stats['failed']++;
                        Log::error("syncAllFrom: user {$user->id} failed: {$e->getMessage()}");
                    }

                    if ($progress) {
                        $progress($user, $stats);
                    }

                    // ⏸️ فاصله بین درخواست‌ها — محافظت در برابر WAF
                    if ($delayMs > 0) {
                        usleep($delayMs * 1000);
                    }
                }
            });

        Log::info('Training syncAllFrom completed', [
            'from'  => $fromDate,
            'stats' => $stats,
        ]);

        return $stats;
    }

    /**
     * محاسبه بازه تاریخ به فرمت YYYYMMDD شمسی
     */
    private function calculateDateRange(bool $fullSync): array
    {
        // تاریخ امروز به شمسی
        $today = \Morilog\Jalali\Jalalian::now();
        $toDate = $today->format('Ymd');

        if ($fullSync) {
            // از 1380/01/01 تا امروز
            $fromDate = '13800101';
        } else {
            // از 6 ماه قبل تا امروز
            $sixMonthsAgo = $today->subMonths(6);
            $fromDate = $sixMonthsAgo->format('Ymd');
        }

        return [$fromDate, $toDate];
    }

    /**
     * پاکسازی مقدار فیلد (حذف "-" و خالی)
     */
    private function cleanField(?string $value): ?string
    {
        if ($value === null) return null;
        $value = trim($value);
        if ($value === '-' || $value === '') return null;
        return $value;
    }

    private function logSync(int $userId, string $status, int $count, ?string $error, float $startTime): void
    {
        try {
            HRSyncLog::create([
                'user_id' => $userId,
                'sync_type' => 'training',
                'status' => $status,
                'records_synced' => $count,
                'error_message' => $error,
                'duration_ms' => round((microtime(true) - $startTime) * 1000),
                'trigger_source' => app()->runningInConsole() ? 'cli' : 'api',
            ]);
        } catch (\Exception $e) {
            Log::error("logSync: save record failed", [$e->getMessage(), $e->getTraceAsString()]);
        }
    }


    /**
     * ✅ کشف تاریخ دوره‌ها با تکنیک تنگ‌کردن بازه
     *
     * سطح ۱: اسکن سال‌ها (14 فراخوانی)
     * سطح ۲: اسکن ماه‌های دارای دوره (12 فراخوانی به ازای هر سال)
     */
    public function enrichDatesForUser(User $user, bool $monthLevel = true, int $fromYear = 1392): array
    {
        $stats = ['years_scanned' => 0, 'months_scanned' => 0, 'updated' => 0];

        if (empty($user->national_code)) return $stats;

        $trainings = EmployeeTraining::where('user_id', $user->id)->get();
        if ($trainings->isEmpty()) return $stats;

        $toYear = (int) \Morilog\Jalali\Jalalian::now()->getYear();

        // ── سطح ۱: اسکن سال‌ها ──
        $yearsWithCourses = [];

        for ($year = $fromYear; $year <= $toYear; $year++) {
            $stats['years_scanned']++;

            $records = $this->fetchRecords($user->national_code, "{$year}0101", "{$year}1229");

            foreach ($records as $record) {
                $code = $record['course_code'] ?? null;
                if (!$code) continue;

                $yearsWithCourses[$year] = true;

                EmployeeTraining::where('user_id', $user->id)
                    ->where('course_code', $code)
                    ->update([
                        'start_year'     => \DB::raw("COALESCE(start_year, {$year})"),
                        'end_year'       => $year,
                        'date_precision' => 'year',
                    ]);
            }

            usleep(150000); // محافظت WAF
        }

        // ── سطح ۲: اسکن ماه‌ها (فقط سال‌های دارای دوره) ──
        if ($monthLevel) {
            foreach (array_keys($yearsWithCourses) as $year) {
                for ($month = 1; $month <= 12; $month++) {
                    $stats['months_scanned']++;

                    $lastDay = $month <= 6 ? 31 : ($month <= 11 ? 30 : 29);
                    $from = sprintf('%d%02d01', $year, $month);
                    $to   = sprintf('%d%02d%02d', $year, $month, $lastDay);

                    $records = $this->fetchRecords($user->national_code, $from, $to);

                    foreach ($records as $record) {
                        $code = $record['course_code'] ?? null;
                        if (!$code) continue;

                        EmployeeTraining::where('user_id', $user->id)
                            ->where('course_code', $code)
                            ->update([
                                'start_year'     => \DB::raw("COALESCE(start_year, {$year})"),
                                'start_month'    => \DB::raw("COALESCE(start_month, {$month})"),
                                'end_year'       => $year,
                                'end_month'      => $month,
                                'date_precision' => 'month',
                            ]);
                    }

                    usleep(120000);
                }
            }
        }

        $stats['updated'] = EmployeeTraining::where('user_id', $user->id)
            ->whereNotNull('start_year')
            ->count();

        return $stats;
    }

    /**
     * فراخوانی SOAP و پارس رکوردها
     */
    private function fetchRecords(string $nationalCode, string $from, string $to): array
    {
        $xml = $this->soapClient->fetchRaw($nationalCode, $from, $to);
        return $xml ? $this->soapClient->parseResponse($xml) : [];
    }
}
