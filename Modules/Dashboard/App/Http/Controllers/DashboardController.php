<?php

namespace Modules\Dashboard\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Dashboard\App\Http\Models\Announcement;
use Modules\HR\App\Jobs\SyncUserHRDataJob;
use Modules\HR\App\Jobs\SyncUserPositionJob;
use Modules\HR\App\Models\EmployeePosition;
use Modules\HR\App\Services\GtarabarSyncService;

class DashboardController extends Controller
{


    /**
     * دریافت اطلاعات کامل داشبورد
     */
    public function getDashboardData(Request $request)
    {
        $user = $request->user();

        // ✅ سمت و واحد از جدول محلی (نه گستراب)
        $position = EmployeePosition::with('unit')
            ->where('user_id', $user->id)
            ->first();

        // بار اول یا داده قدیمی → sync در پس‌زمینه
        if (!$position && !empty($user->personnel_code)) {
            dispatch(new SyncUserHRDataJob($user, 'dashboard'))->afterResponse();
        } elseif ($position && $position->isStale(15)) {
            dispatch(new SyncUserHRDataJob($user, 'dashboard'))->afterResponse();
        }

        // ساخت پروفایل
        $profile = [
            'id'              => $user->id,
            'name'            => $user->name,
            'mobile'          => $user->mobile,
            'email'           => $user->email,
            'personnel_code'  => $user->personnel_code,
            'national_code'   => $user->national_code,
            'employee_id'     => $position?->gt_employee_id,
            'post'            => [
                'code'  => $position?->post_code,
                'title' => $position?->post_title,
            ],
            'job'             => [
                'code'  => $position?->job_code,
                'title' => $position?->job_title,
            ],
            // ✅ واحد سازمانی — جدید
            'unit'            => $position?->unit ? [
                'id'    => $position->unit->id,
                'title' => $position->unit->title,
            ] : null,
        ];

        // فیش و مرخصی همچنان از گستراب (مثل قبل)
        $months      = $user->getAvailablePayslipMonths();
        $latestMonth = $months[0] ?? null;
        $latestPayslip = $latestMonth
            ? $user->getPayslipSummary($latestMonth)
            : null;

        $leaveData = $user->getLeaveDashboardData($latestMonth, $months);

        return response()->json([
            'profile'        => $profile,
            'latest_payslip' => $latestPayslip,
            'latest_month'   => $latestMonth,
            'leave'          => $leaveData,
            'roles'          => $user->getRoleNames()->values(),
            'permissions'    => $user->getAllPermissions()->pluck('name')->values(),
            // ✅ اطلاعات sync برای نمایش در فرانت
            'position_synced_at'   => $position?->synced_at,
            'position_sync_failed' => $position?->sync_failed ?? false,
        ]);
    }

    /**
     * دریافت اعلانات
     */
    /**
     * دریافت اعلانات (سراسری + اختصاصی کاربر)
     */
    public function getAnnouncements(Request $request)
    {
        $user = $request->user();

        $announcements = Announcement::active()
            ->forUser($user->id)  // ✅ فقط اعلانات سراسری + اختصاصی این کاربر
            ->orderBy('published_at', 'desc')
            ->limit(20)
            ->get()
            ->map(function ($announcement) use ($user) {
                return [
                    'id' => $announcement->id,
                    'user_id' => $announcement->user_id,  // ✅ اضافه شد
                    'title' => $announcement->title,
                    'description' => $announcement->description,
                    'type' => $announcement->type,
                    'is_global' => is_null($announcement->user_id),  // ✅ اضافه شد
                    'is_read' => $announcement->isReadBy($user),
                    'created_at' => $announcement->created_at,
                    'published_at' => $announcement->published_at,
                ];
            });

        return response()->json([
            'announcements' => $announcements,
        ]);
    }

    /**
     * علامت‌گذاری اعلان به عنوان خوانده‌شده
     */
    public function markAsRead(Request $request, $id)
    {
        $user = $request->user();

        $announcement = Announcement::active()
            ->forUser($user->id)
            ->findOrFail($id);

        if (!$announcement->isReadBy($user)) {
            $announcement->readers()->attach($user->id, [
                'read_at' => now(),
            ]);
        }

        return response()->json([
            'message' => 'اعلان به عنوان خوانده‌شده علامت‌گذاری شد.',
        ]);
    }

    /**
     * دریافت خلاصه فیش حقوقی (برای backward compatibility)
     */
    public function getPayslipSummary(Request $request)
    {
        $user = $request->user();

        $months = $user->getAvailablePayslipMonths();
        $latestMonth = $months[0] ?? null;
        $payslip = null;

        if ($latestMonth) {
            $payslip = $user->getPayslipSummary($latestMonth);
        }

        return response()->json([
            'payslip' => $payslip,
            'month' => $latestMonth,
        ]);
    }
}
