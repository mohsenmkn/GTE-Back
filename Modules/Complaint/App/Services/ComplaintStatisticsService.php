<?php

namespace Modules\Complaint\App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Complaint\App\Enums\ComplaintPriority;
use Modules\Complaint\App\Enums\ComplaintStatus;
use Modules\Complaint\App\Models\Complaint;
use Modules\Complaint\App\Models\ComplaintCategory;
use Morilog\Jalali\Jalalian;

class ComplaintStatisticsService
{
    public function getDashboardStatistics(): array
    {
        return [
            'overview'         => $this->getOverview(),
            'by_status'        => $this->getByStatus(),
            'by_priority'      => $this->getByPriority(),
            'by_domain'        => $this->getByDomain(),
            'top_categories'   => $this->getTopCategories(10),
            'monthly_trend'    => $this->getMonthlyTrend(12),
            'response_stats'   => $this->getResponseStats(),
            'recent_complaints' => $this->getRecentComplaints(8),
        ];
    }

    // ─────────────────────────────────────
    // Overview
    // ─────────────────────────────────────
    private function getOverview(): array
    {
        $total = Complaint::count();

        $byStatus = Complaint::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $byPriority = Complaint::selectRaw('priority, COUNT(*) as count')
            ->groupBy('priority')
            ->pluck('count', 'priority');

        $openCount = ($byStatus[ComplaintStatus::Pending->value] ?? 0)
            + ($byStatus[ComplaintStatus::InProgress->value] ?? 0);

        $criticalOpen = Complaint::whereIn('status', [
            ComplaintStatus::Pending->value,
            ComplaintStatus::InProgress->value,
        ])
            ->where('priority', ComplaintPriority::Critical->value)
            ->count();

        return [
            'total'          => $total,
            'open'           => $openCount,
            'pending'        => $byStatus[ComplaintStatus::Pending->value] ?? 0,
            'in_progress'    => $byStatus[ComplaintStatus::InProgress->value] ?? 0,
            'answered'       => $byStatus[ComplaintStatus::Answered->value] ?? 0,
            'resolved'       => $byStatus[ComplaintStatus::Resolved->value] ?? 0,
            'rejected'       => $byStatus[ComplaintStatus::Rejected->value] ?? 0,
            'critical_open'  => $criticalOpen,
        ];
    }

    // ─────────────────────────────────────
    // By Status (for doughnut chart)
    // ─────────────────────────────────────
    private function getByStatus(): array
    {
        $data = Complaint::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $result = [];
        foreach (ComplaintStatus::cases() as $case) {
            $result[] = [
                'value' => $case->value,
                'label' => $case->label(),
                'count' => $data[$case->value] ?? 0,
            ];
        }

        return $result;
    }

    // ─────────────────────────────────────
    // By Priority (for bar chart)
    // ─────────────────────────────────────
    private function getByPriority(): array
    {
        $data = Complaint::selectRaw('priority, COUNT(*) as count')
            ->groupBy('priority')
            ->pluck('count', 'priority');

        $result = [];
        foreach (ComplaintPriority::cases() as $case) {
            $result[] = [
                'value' => $case->value,
                'label' => $case->label(),
                'count' => $data[$case->value] ?? 0,
            ];
        }

        return $result;
    }

    // ─────────────────────────────────────
    // By Domain (level 1 categories)
    // ─────────────────────────────────────
    private function getByDomain(): array
    {
        $results = Complaint::query()
            ->join('complaint_categories as items', 'complaints.complaint_category_id', '=', 'items.id')
            ->join('complaint_categories as subs', 'items.parent_id', '=', 'subs.id')
            ->join('complaint_categories as domains', 'subs.parent_id', '=', 'domains.id')
            ->select('domains.id', 'domains.title', DB::raw('COUNT(complaints.id) as count'))
            ->groupBy('domains.id', 'domains.title')
            ->orderByDesc('count')
            ->get();

        $total = $results->sum('count');

        return $results->map(function ($item) use ($total) {
            return [
                'id'         => $item->id,
                'title'      => $item->title,
                'count'      => $item->count,
                'percentage' => $total > 0 ? round(($item->count / $total) * 100, 1) : 0,
            ];
        })->toArray();
    }

    // ─────────────────────────────────────
    // Top Categories (level 3 items)
    // ─────────────────────────────────────
    private function getTopCategories(int $limit = 10): array
    {
        return Complaint::query()
            ->join('complaint_categories', 'complaints.complaint_category_id', '=', 'complaint_categories.id')
            ->select(
                'complaint_categories.id',
                'complaint_categories.title',
                DB::raw('COUNT(complaints.id) as count')
            )
            ->groupBy('complaint_categories.id', 'complaint_categories.title')
            ->orderByDesc('count')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    // ─────────────────────────────────────
    // Monthly Trend (last N jalali months)
    // ─────────────────────────────────────
    private function getMonthlyTrend(int $months = 12): array
    {
        $now = Jalalian::now();
        $result = [];

        // Generate last N months
        for ($i = $months - 1; $i >= 0; $i--) {
            $carbonDate = Carbon::now()->subMonths($i);
            $jalaliDate = Jalalian::fromCarbon($carbonDate);

            $yearMonth = $jalaliDate->format('Y-m');

            $result[$yearMonth] = [
                'month'      => $yearMonth,
                'month_name' => $this->getPersianMonthName($jalaliDate->getMonth()),
                'year'       => $jalaliDate->getYear(),
                'count'      => 0,
            ];
        }

        // Get actual counts from DB
        $startDate = Carbon::now()->subMonths($months - 1)->format('Y-m-01');
        $jalaliStart = Jalalian::fromCarbon(Carbon::parse($startDate));
        $jalaliStartStr = $jalaliStart->format('Y-m') . '-01';

        // ✅ اصلاح شد: year_month یک کلمه رزرو در MySQL است
        // از complaint_month استفاده می‌کنیم
        $counts = Complaint::query()
            ->selectRaw("LEFT(jalali_date, 7) as complaint_month, COUNT(*) as count")
            ->where('jalali_date', '>=', $jalaliStartStr)
            ->groupBy('complaint_month')
            ->pluck('count', 'complaint_month');

        // Fill in actual counts
        foreach ($counts as $yearMonth => $count) {
            if (isset($result[$yearMonth])) {
                $result[$yearMonth]['count'] = (int) $count;
            }
        }

        return array_values($result);
    }

    // ─────────────────────────────────────
    // Response Statistics
    // ─────────────────────────────────────
    private function getResponseStats(): array
    {
        $totalCount = Complaint::count();
        $answeredCount = Complaint::whereNotNull('answered_at')->count();

        $avgResponseHours = Complaint::whereNotNull('answered_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, created_at, answered_at)) as avg_hours')
            ->value('avg_hours');

        $minResponseHours = Complaint::whereNotNull('answered_at')
            ->selectRaw('MIN(TIMESTAMPDIFF(HOUR, created_at, answered_at)) as min_hours')
            ->value('min_hours');

        $maxResponseHours = Complaint::whereNotNull('answered_at')
            ->selectRaw('MAX(TIMESTAMPDIFF(HOUR, created_at, answered_at)) as max_hours')
            ->value('max_hours');

        return [
            'avg_response_hours'  => round($avgResponseHours ?? 0, 1),
            'min_response_hours'  => round($minResponseHours ?? 0, 1),
            'max_response_hours'  => round($maxResponseHours ?? 0, 1),
            'response_rate'       => $totalCount > 0
                ? round(($answeredCount / $totalCount) * 100, 1)
                : 0,
            'total_answered'      => $answeredCount,
            'total_unanswered'    => $totalCount - $answeredCount,
        ];
    }

    // ─────────────────────────────────────
    // Recent Complaints
    // ─────────────────────────────────────
    private function getRecentComplaints(int $limit = 8): array
    {
        return Complaint::query()
            ->with(['category', 'user'])
            ->latest()
            ->limit($limit)
            ->get()
            ->map(function ($complaint) {
                return [
                    'id'            => $complaint->id,
                    'tracking_code' => $complaint->tracking_code,
                    'subject'       => $complaint->subject,
                    'status'        => $complaint->status?->value,
                    'status_label'  => $complaint->status?->label(),
                    'priority'      => $complaint->priority?->value,
                    'priority_label' => $complaint->priority?->label(),
                    'jalali_date'   => $complaint->jalali_date,
                    'category_title' => $complaint->category?->title,
                    'complainant'   => $complaint->user?->name,
                ];
            })
            ->toArray();
    }

    // ─────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────
    private function getPersianMonthName(int $month): string
    {
        $months = [
            1 => 'فروردین', 2 => 'اردیبهشت', 3 => 'خرداد',
            4 => 'تیر', 5 => 'مرداد', 6 => 'شهریور',
            7 => 'مهر', 8 => 'آبان', 9 => 'آذر',
            10 => 'دی', 11 => 'بهمن', 12 => 'اسفند',
        ];

        return $months[$month] ?? '';
    }
}
