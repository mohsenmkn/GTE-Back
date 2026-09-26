<?php

namespace Modules\Library\App\Services\Sms;

use Modules\Library\App\Models\Notification;
use Modules\Library\App\Models\Reservation;
use Morilog\Jalali\Jalalian;
use Carbon\Carbon;

class SmsManager
{
    private MsgwayService $smsService;

    public function __construct(MsgwayService $smsService)
    {
        $this->smsService = $smsService;
    }

    public function sendDueReminder(Reservation $reservation): bool
    {
        $user = $reservation->user;
        $bookTitle = $reservation->bookCopy->book->title;
        $dueDate = $reservation->expected_return_date->format('Y/m/d');

        $messageText = "یادآوری: ۳ روز تا سررسید کتاب «{$bookTitle}» باقی مانده ({$dueDate}). لطفاً نسبت به بازگرداندن اقدام کنید. - کتابخانه گهرترابر";

        return $this->sendAndLog($user, $reservation, 'reminder', $messageText);
    }


    private function sendAndLog($user, $reservation, string $type, string $messageText): bool
    {
        // جلوگیری از ارسال تکراری
        $alreadySent = Notification::where('reservation_id', $reservation->id)
            ->where('type', $type)
            ->whereNotNull('sent_at')
            ->exists();

        if ($alreadySent) {
            return false;
        }

        // ارسال پیامک
        $result = $this->smsService->send($user->mobile, $messageText);

        // ثبت در دیتابیس
        Notification::create([
            'user_id' => $user->id,
            'reservation_id' => $reservation->id,
            'type' => $type,
            'message' => $messageText, // متن کامل ذخیره می‌شود
            'mobile' => $user->mobile,
            'sent_at' => $result['success'] ? Carbon::now() : null,
            'is_read' => false,
        ]);

        return $result['success'];
    }

    /**
     * ارسال پیامک ثبت اولیه رزرو (در انتظار تایید)
     */
    public function sendPendingNotification(Reservation $reservation): bool
    {
        $user = $reservation->user;
        $bookTitle = $reservation->bookCopy->book->title;
        //$pickupDate = $reservation->expected_pickup_date->format('Y/m/d');
        $pickupDate = $this->toShamsi($reservation->expected_pickup_date);
        // اگر برای این حالت هم الگوی جداگانه دارید، پارامترها را اینجا تنظیم کنید
        $templateParams = [
            $user->name,
            $bookTitle,
            $pickupDate
        ];
        $logMessage = "ثبت درخواست رزرو کتاب: {$bookTitle} در تاریخ {$pickupDate}";
        //$messageText = "کاربر گرامی {$user->name}، درخواست رزرو کتاب «{$bookTitle}» با موفقیت ثبت شد و در انتظار تایید مدیر کتابخانه است. پس از تایید، در تاریخ {$pickupDate} جهت تحویل مراجعه فرمایید. - کتابخانه گهرترابر";
        return $this->sendPatternAndLog($user, $reservation, 'pending', $templateParams, $logMessage);

       // return $this->sendAndLog($user, $reservation, 'pending', $messageText);
    }

    /**
     * ارسال پیامک تایید رزرو توسط مدیر
     */
    public function sendApprovalNotification(Reservation $reservation): bool
    {
        $user = $reservation->user;
        $bookTitle = $reservation->bookCopy->book->title;
        //$pickupDate = $reservation->expected_pickup_date->format('Y/m/d');
        //$dueDate = $reservation->expected_return_date->format('Y/m/d');
        $pickupDate = $this->toShamsi($reservation->expected_pickup_date);
        $dueDate = $this->toShamsi($reservation->expected_return_date);

        $templateParams = [
            $bookTitle,   // [param1]
            $pickupDate,  // [param2]
            $dueDate      // [param3]
        ];
        // متن خلاصه برای ذخیره در دیتابیس (سابقه)
        $logMessage = "تایید رزرو کتاب: {$bookTitle} | تحویل: {$pickupDate} | بازگشت: {$dueDate}";

        //$messageText = "رزرو کتاب «{$bookTitle}» تایید شد. لطفاً در تاریخ {$pickupDate} جهت تحویل به کتابخانه مراجعه فرمایید. مهلت بازگشت: {$dueDate}. - کتابخانه گهرترابر";
        return $this->sendPatternAndLog($user, $reservation, 'approval', $templateParams, $logMessage);

       // return $this->sendAndLog($user, $reservation, 'approval', $messageText);
    }

    private function sendPatternAndLog($user, $reservation, string $type, array $templateParams, string $logMessage): bool
    {
        // جلوگیری از ارسال تکراری
        $alreadySent = Notification::where('reservation_id', $reservation->id)
            ->where('type', $type)
            ->whereNotNull('sent_at')
            ->exists();

        if ($alreadySent) {
            return false;
        }

        if ($type !== null) {
            if ($type === 'approval') {
                $result = $this->smsService->sendPattern(
                    $user->mobile,
                    $templateParams,
                    23490
                );
            } elseif ($type === 'pending') {
                $result = $this->smsService->sendPattern(
                    $user->mobile,
                    $templateParams,
                    23492
                );
            }
        }


        // ثبت در دیتابیس
        Notification::create([
            'user_id' => $user->id,
            'reservation_id' => $reservation->id,
            'type' => $type,
            'message' => $logMessage,
            'mobile' => $user->mobile,
            'sent_at' => $result['success'] ? Carbon::now() : null,
            'is_read' => false,
        ]);
        return $result['success'];
    }


    /**
     * 🔑 helper برای تبدیل تاریخ میلادی به شمسی با فرمت 1405/05/20
     */
    private function toShamsi($date): string
    {
        if (!$date) return '-';

        // اگر رشته بود، به Carbon تبدیل کن
        if (is_string($date)) {
            $date = Carbon::parse($date);
        }

        return Jalalian::fromDateTime($date)->format('Y/m/d');
    }


}
