<?php

namespace Modules\Library\App\Services\Sms;

interface SmsServiceInterface
{
    /**
     * ارسال پیامک
     * @param string $mobile شماره موبایل مقصد
     * @param string $message متن پیامک
     * @return array ['success' => bool, 'message_id' => string|null, 'error' => string|null]
     */
    public function send(string $mobile, string $message): array;

    /**
     * بررسی وضعیت ارسال پیامک
     */
    public function checkStatus(string $messageId): array;
}
