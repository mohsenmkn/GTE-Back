<?php

namespace Modules\Complaint\App\Services;

use Illuminate\Support\Facades\Log;
use Modules\Complaint\App\Models\Complaint;
use Modules\Complaint\App\Models\ComplaintSmsLog;
use Throwable;

class ComplaintSmsService
{
    protected $sender = null;

    public function __construct()
    {
        $msgwayClass = \Modules\Library\App\Services\Sms\MsgwayService::class;

        if (class_exists($msgwayClass)) {
            $this->sender = app($msgwayClass);
        }
    }

    public function sendRepliedSms(Complaint $complaint): ?ComplaintSmsLog
    {
        if (! config('complaint.sms.enabled', true)) {
            return null;
        }

        $user = $complaint->user;

        if (! $user) {
            return null;
        }

        $mobile = $user->mobile;

        if (empty($mobile) && method_exists($user, 'getGtarabarEmployee')) {
            try {
                $employee = $user->getGtarabarEmployee();
                $mobile = $employee->Mobile ?? null;
            } catch (Throwable $e) {
                Log::error('Complaint SMS: error while getting employee mobile', [
                    'complaint_id' => $complaint->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $message = $complaint->tracking_code;

        $result = [
            'success' => false,
            'message_id' => null,
            'error' => 'SMS sender not found.',
        ];

        try {
            if ($this->sender) {
                //$templateId = config('complaint.sms.template_id');
                $result = $this->sender->sendPattern($mobile, [$message], 23528);
            }
        } catch (Throwable $e) {
            Log::error('Complaint SMS exception', [
                'complaint_id' => $complaint->id,
                'mobile' => $mobile,
                'error' => $e->getMessage(),
            ]);

            $result = [
                'success' => false,
                'message_id' => null,
                'error' => $e->getMessage(),
            ];
        }

        return ComplaintSmsLog::create([
            'complaint_id' => $complaint->id,
            'mobile' => $mobile ?: 'invalid',
            'message' => $message,
            'status' => data_get($result, 'success') ? 'sent' : 'failed',
            'provider_response' => $result,
            'sent_at' => data_get($result, 'success') ? now() : null,
        ]);
    }
}
