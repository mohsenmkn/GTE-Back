<?php


namespace Modules\VirtualSecretariat\App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\VirtualSecretariat\App\Models\VsRequest;
use Modules\Library\App\Services\Sms\MsgwayService;
use Modules\Dashboard\App\Http\Models\Announcement; // ✅ اضافه کردن مدل اعلانات

class AdminLetterService
{
    // ✅ تزریق سرویس پیامک در کانستراکتور
    public function __construct(MsgwayService $smsService)
    {
        $this->smsService = $smsService;
    }


    /**
     * تغییر وضعیت درخواست
     */
    public function updateRequestStatus(
        VsRequest $request,
        string $newStatus,
        ?string $adminNote,
        int $adminUserId
    ): VsRequest {
        DB::beginTransaction();

        try {
            $request->update([
                'status' => $newStatus,
                'admin_note' => $adminNote,
                'admin_user_id' => $adminUserId,
                'completed_at' => in_array($newStatus, ['completed', 'rejected', 'cancelled'])
                    ? now()
                    : $request->completed_at,
            ]);

            Log::info("Admin {$adminUserId} changed request #{$request->id} status to {$newStatus}", [
                'admin_note' => $adminNote,
            ]);

            DB::commit();

            // ✅ ارسال پیامک پس از موفقیت‌آمیز بودن تغییر وضعیت
            $this->sendStatusChangeSms($request, $newStatus);

            // ✅ 2. ایجاد اعلان در تابلو اعلانات
            $this->createInAppAnnouncement($request, $newStatus);

            return $request->fresh() ?? $request;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating request status: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * تلاش مجدد برای درخواست ناموفق
     */
    public function retryFailedRequest(VsRequest $request): VsRequest
    {
        $request->update([
            'status' => 'pending',
            'error_message' => null,
            'automation_entity_code' => null,
            'automation_letter_number' => null,
            'sent_at' => null,
        ]);

        return $request->fresh();
    }

    /**
     * ✅ متد جدید: ارسال پیامک تغییر وضعیت به کاربر
     */
    private function sendStatusChangeSms(VsRequest $request, string $status): void
    {
        // 1. بررسی وجود کاربر و شماره موبایل
        $user = $request->user;
        if (!$user || empty($user->mobile)) {
            Log::warning("Cannot send SMS: User or mobile number is missing for request #{$request->id}");
            return;
        }

        // 2. نگاشت وضعیت به متن فارسی
        $statusLabels = [
            'completed' => 'تکمیل',
            'rejected' => 'رد',
            'cancelled' => 'لغو',
            'failed' => 'ناموفق',
            'sent' => 'ثبت و ارسال'
        ];

        $statusText = $statusLabels[$status] ?? 'به‌روزرسانی';

        // 3. ساخت متن پیامک
        $message = "کاربر گرامی،\nدرخواست شما با عنوان «{$request->title}» در دبیرخانه مجازی {$statusText} شد.\nلطفاً برای مشاهده جزئیات به پنل کاربری مراجعه کنید.";

        // 4. فراخوانی سرویس پیامک (MsgwayService)
        $result = $this->smsService->send($user->mobile, $message);

        // 5. ثبت نتیجه در لاگ
        if ($result['success']) {
            Log::info("SMS sent successfully to {$user->mobile} for request #{$request->id}");

            // نکته: اگر مدل Notification سراسری دارید، می‌توانید اینجا رکورد آن را هم ذخیره کنید.
            // در غیر این صورت، لاگ لاراول برای ردیابی کافی است.
        } else {
            Log::error("SMS failed for request #{$request->id} (Mobile: {$user->mobile}). Error: " . ($result['error'] ?? 'Unknown'));
        }
    }


    /**
     * ✅ متد جدید: ایجاد اعلان در تابلو اعلانات برای کاربر
     */
    private function createInAppAnnouncement(VsRequest $request, string $status): void
    {
        $user = $request->user;
        if (!$user) return;

        $statusLabels = [
            'completed' => 'تکمیل',
            'rejected' => 'رد',
            'cancelled' => 'لغو',
            'failed' => 'ناموفق',
            'sent' => 'ثبت و ارسال'
        ];
        $statusText = $statusLabels[$status] ?? 'به‌روزرسانی';

        $title = "تغییر وضعیت درخواست دبیرخانه مجازی";
        $description = "کاربر گرامی، درخواست شما با عنوان «<strong>{$request->title}</strong>» در دبیرخانه مجازی <strong>{$statusText}</strong> شد.<br>لطفاً برای مشاهده جزئیات و گردش کار، به بخش <em>درخواست‌های من</em> مراجعه کنید.";

        try {
            Announcement::create([
                'user_id' => $user->id,          // ✅ اختصاصی برای این کاربر
                'title' => $title,
                'description' => $description,
                'type' => 'virtual_secretariat', // نوع اعلان برای فیلتر کردن در فرانت
                'is_active' => true,
                'published_at' => Carbon::now(),
                'expires_at' => Carbon::now()->addDays(30), // اعلان پس از ۳۰ روز منقضی می‌شود
            ]);

            Log::info("In-app announcement created for user {$user->id} regarding request #{$request->id}");
        } catch (\Exception $e) {
            Log::error("Failed to create in-app announcement: " . $e->getMessage());
        }
    }


}
