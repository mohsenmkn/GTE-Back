<?php

namespace Modules\Complaint\App\Listeners;

use Modules\Complaint\App\Events\ComplaintReplied;
use Modules\Complaint\App\Services\ComplaintSmsService;

class SendComplaintReplySms
{
    public function __construct(
        protected ComplaintSmsService $smsService
    ) {
    }

    public function handle(ComplaintReplied $event): void
    {
        // اگر یادداشت داخلی باشد، پیامک ارسال نمی‌شود
        if ($event->reply->is_internal) {
            return;
        }

        // ارسال پیامک به شاکی
        $log = $this->smsService->sendRepliedSms($event->complaint);

        // ✅ اصلاح شد: reply یک پراپرتی است، نه متد
        $event->reply->update([
            'sent_sms' => $log && $log->status === 'sent',
        ]);
    }
}
