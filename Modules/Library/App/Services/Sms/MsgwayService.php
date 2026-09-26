<?php

namespace Modules\Library\App\Services\Sms;

use Illuminate\Support\Facades\Log;

class MsgwayService implements SmsServiceInterface
{
    private string $apiKey;
    private int $provider;
    private int $defaultTemplateId;

    public function __construct()
    {
        $this->apiKey = config('services.msgway.api_key');
        $this->provider = (int) config('services.msgway.provider');
        $this->defaultTemplateId = (int) config('services.msgway.template_id_default', 16860);
    }

    /**
     * ارسال پیامک با دریافت یک رشته متنی کامل
     */
    public function send(
        string $mobile,
        string $messageText,
        ?string $templateId = null
    ): array {
        // اگر templateId ارسال نشده باشد، از مقدار پیش‌فرض کلاس استفاده کن
        $templateId = $templateId ?? $this->defaultTemplateId;

        // نرمال‌سازی شماره موبایل
        $mobile = preg_replace('/[\s\-\+]/', '', $mobile);

        if (str_starts_with($mobile, '989')) {
            $mobile = '0' . substr($mobile, 2);
        }

        if (!preg_match('/^09\d{9}$/', $mobile)) {
            return [
                'success' => false,
                'message_id' => null,
                'error' => 'شماره موبایل نامعتبر است.',
            ];
        }

        // پارامترهای ارسال پیامک
        $params = [
            "mobile" => $mobile,
            "method" => "sms",
            "provider" => $this->provider,
            "templateID" => $templateId,
            "params" => [$messageText],
        ];

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://api.msgway.com/send',
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => json_encode($params),
            CURLOPT_HTTPHEADER => [
                'apiKey: ' . $this->apiKey,
                'Content-Type: application/json',
            ],
        ]);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);

        curl_close($curl);

        if ($error) {
            Log::channel('sms')->error(
                "SMS cURL Error for {$mobile}: " . $error
            );

            return [
                'success' => false,
                'message_id' => null,
                'error' => $error,
            ];
        }

        Log::channel('sms')->info(
            "SMS Response for {$mobile} (HTTP {$httpCode}): " . $response
        );

        $isSuccess = $httpCode >= 200 && $httpCode < 300;

        // بررسی خطای داخلی راه پیام
        if ($isSuccess) {
            $responseData = json_decode($response, true);

            if (
                isset($responseData['status']) &&
                $responseData['status'] !== 'success'
            ) {
                $isSuccess = false;
                $error = $responseData['message'] ?? 'خطای ناشناخته از سمت پنل';
            }
        }

        return [
            'success' => $isSuccess,
            'message_id' => $isSuccess ? 'sent_via_msgway' : null,
            'error' => $isSuccess
                ? null
                : "HTTP Error: {$httpCode} | Response: {$response}",
        ];
    }




    public function checkStatus(string $messageId): array
    {
        return ['success' => true, 'data' => []];
    }

    public function sendPattern(string $mobile, array $params, ?int $templateId = null): array
    {
        // نرمال‌سازی شماره موبایل
        $mobile = preg_replace('/[\s\-\+]/', '', $mobile);
        if (str_starts_with($mobile, '989')) {
            $mobile = '0' . substr($mobile, 2);
        }

        if (!preg_match('/^09\d{9}$/', $mobile)) {
            return [
                'success' => false,
                'message_id' => null,
                'error' => 'شماره موبایل نامعتبر است.',
            ];
        }

        $payload = [
            "mobile" => $mobile,
            "method" => "sms",
            "provider" => $this->provider,
            "templateID" => $templateId ?: $this->defaultTemplateId,
            "params" => $params // 🔑 اینجا آرایه پارامترها مستقیماً ارسال می‌شود
        ];

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api.msgway.com/send',
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => array(
                'apiKey: ' . $this->apiKey,
            ),
        ));

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($error) {
            Log::channel('sms')->error("SMS cURL Error for {$mobile}: " . $error);
            return ['success' => false, 'message_id' => null, 'error' => $error];
        }

        Log::channel('sms')->info("SMS Pattern Response for {$mobile} (HTTP {$httpCode}): " . $response);

        $isSuccess = $httpCode >= 200 && $httpCode < 300;
        // بررسی پاسخ داخلی پنل
        if ($isSuccess) {
            $responseData = json_decode($response, true);
            if (isset($responseData['status']) && $responseData['status'] !== 'success') {
                $isSuccess = false;
                $error = $responseData['message'] ?? 'خطای ناشناخته از سمت پنل';
            }
        }

        return [
            'success' => $isSuccess,
            'message_id' => $isSuccess ? 'sent_via_msgway' : null,
            'error' => $isSuccess ? null : "HTTP Error: {$httpCode} | Response: {$response}",
        ];
    }


    }
