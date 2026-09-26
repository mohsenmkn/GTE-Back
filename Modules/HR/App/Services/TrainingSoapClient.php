<?php

namespace Modules\HR\App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TrainingSoapClient
{
    private string $url = 'https://training.geg.ir/IdeaWeb/Apps/Services/TrainingWS.asmx';
    private int $timeout = 30;

    /**
     * 🎯 استراتژی‌های هدر — به ترتیب امتحان می‌شوند
     *
     * استراتژی ۱ دقیقاً مشابه حالت موفق Postman است:
     * فقط Content-Type فعال، بدون SOAPAction
     */
    private function headerStrategies(): array
    {
        return [
            // ۱) دقیقاً مثل Postman (بدون SOAPAction)
            [
                'Content-Type' => 'text/xml; charset=utf-8',
                'Accept'       => '*/*',
                'User-Agent'   => 'PostmanRuntime/7.36.0',
            ],
            // ۲) مثل Postman + SOAPAction
            [
                'Content-Type' => 'text/xml; charset=utf-8',
                'SOAPAction'   => 'http://tempuri.org/GetTotalDataJson',
                'Accept'       => '*/*',
                'User-Agent'   => 'PostmanRuntime/7.36.0',
            ],
            // ۳) User-Agent مرورگر
            [
                'Content-Type' => 'text/xml; charset=utf-8',
                'User-Agent'   => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36',
            ],
        ];
    }

    /**
     * فراخوانی SOAP با استراتژی‌های چندگانه هدر
     */
    public function fetchRaw(
        string $nationalCode,
        string $fromDate,
        string $toDate,
        string $title = 'PersonelTraining'
    ): ?string {
        $body = $this->buildEnvelope($title, $fromDate, $toDate, $nationalCode);

        foreach ($this->headerStrategies() as $index => $headers) {
            try {
                $response = Http::timeout($this->timeout)
                    ->withHeaders($headers)
                    ->withBody($body, 'text/xml')
                    ->post($this->url);

                $responseBody = $response->body();

                // ❌ تشخیص رد شدن توسط WAF → استراتژی بعدی
                if ($this->isRejected($responseBody)) {
                    Log::warning("Training SOAP: strategy {$index} rejected by WAF", [
                        'headers' => array_keys($headers),
                    ]);
                    continue;
                }

                if (!$response->successful()) {
                    Log::warning("Training SOAP: strategy {$index} returned HTTP {$response->status()}");
                    continue;
                }

                // ✅ موفق
                Log::info("✅ Training SOAP: strategy {$index} succeeded", [
                    'headers' => array_keys($headers),
                ]);
                return $responseBody;
            } catch (\Exception $e) {
                Log::warning("Training SOAP: strategy {$index} exception: {$e->getMessage()}");
            }
        }

        Log::error('Training SOAP: all header strategies failed');
        return null;
    }

    /**
     * آیا پاسخ توسط WAF رد شده؟
     */
    private function isRejected(string $body): bool
    {
        return str_contains($body, 'Request Rejected')
            || str_starts_with(trim($body), '<html');
    }

    // ── بقیه متدها (parseResponse, persianToEnglishKey, slugify, buildEnvelope) بدون تغییر ──

    /**
     * استخراج Support ID از پاسخ WAF
     */
    private function extractSupportId(string $html): ?string
    {
        if (preg_match('/support ID is:\s*(\d+)/i', $html, $m)) {
            return $m[1];
        }
        return null;
    }

    /**
     * پارس XML و برگرداندن آرایه رکوردها — نسخه مقاوم
     */
    public function parseResponse(string $xml): array
    {
        if (empty($xml)) {
            Log::error('Training SOAP: XML خالی است');
            return [];
        }

        // ۱) حذف BOM احتمالی
        $xml = preg_replace('/^\x{FEFF}/u', '', $xml);
        $xml = preg_replace('/^\xEF\xBB\xBF/', '', $xml);

        // ۲) حذف namespace prefixes و declarations
        $xml = preg_replace('/(<\/*)soap:/', '$1', $xml);
        $xml = preg_replace('/(<\/*)diffgr:/', '$1', $xml);
        $xml = preg_replace('/(<\/*)msdata:/', '$1', $xml);
        $xml = preg_replace('/(<\/*)xs:/', '$1', $xml);

        // ۳) حذف کامل namespace declarations
        $xml = preg_replace('/\s+xmlns(:[a-zA-Z0-9_]+)?="[^"]*"/', '', $xml);

        // ۴) فعال‌سازی خطاهای داخلی libxml
        $previousErrors = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $doc = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);

        if ($doc === false) {
            // جمع‌آوری خطاهای XML
            $errors = [];
            foreach (libxml_get_errors() as $error) {
                $errors[] = [
                    'line'    => $error->line,
                    'column'  => $error->column,
                    'message' => trim($error->message),
                    'level'   => $error->level,
                ];
            }

            Log::error('Training SOAP XML parse failed', [
                'errors'      => $errors,
                'xml_sample'  => substr($xml, 0, 800),
            ]);

            libxml_clear_errors();
            libxml_use_internal_errors($previousErrors);
            return [];
        }

        libxml_use_internal_errors($previousErrors);

        // ۵) پیدا کردن همه <outPut> با XPath ساده (بدون namespace)
        $records = $doc->xpath('//outPut');

        if (!$records) {
            // تلاش دوم: جستجو در diffgram/NewDataSet
            $records = $doc->xpath('//NewDataSet/outPut');
        }

        if (!$records || empty($records)) {
            Log::warning('Training SOAP: هیچ رکورد outPut پیدا نشد', [
                'xml_sample' => substr($xml, 0, 500),
            ]);
            return [];
        }

        // ۶) تبدیل رکوردها به آرایه
        $result = [];
        foreach ($records as $record) {
            $row = [];
            foreach ($record->children() as $field) {
                $rawName   = $field->getName();
                $cleanName = str_replace('_x0020_', ' ', $rawName);
                $key       = $this->persianToEnglishKey($cleanName);
                $row[$key] = trim((string) $field);
            }

            // فقط رکوردهایی که کد دوره دارند
            if (!empty($row['course_code'])) {
                $result[] = $row;
            }
        }

        return $result;
    }

    /**
     * تبدیل نام فارسی به کلید انگلیسی
     */
    private function persianToEnglishKey(string $persianName): string
    {
        $map = [
            'کد پرسنلی'    => 'personnel_code',
            'نام'           => 'first_name',
            'نام خانوادگی'  => 'last_name',
            'معاونت'        => 'deputy',
            'مدیریت'        => 'management',
            'پست سازمانی'   => 'post_title',
            'کد دوره'       => 'course_code',
            'عنوان دوره'    => 'course_title',
            'جمع مدت جلسات' => 'session_duration',
            'مدت عملکرد'    => 'performance_hours',
        ];

        return $map[trim($persianName)] ?? $this->slugify($persianName);
    }

    /**
     * ساخت کلید slug برای فیلدهای ناشناخته
     */
    private function slugify(string $text): string
    {
        $text = str_replace(' ', '_', $text);
        $text = preg_replace('/[^\w\x{0600}-\x{06FF}]/u', '', $text);
        return $text ?: 'unknown';
    }

    /**
     * ساخت SOAP Envelope
     */
    private function buildEnvelope(
        string $title,
        string $fromDate,
        string $toDate,
        string $personnelCode
    ): string {
        return <<<XML
<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <GetPersonnelDataByDate xmlns="http://tempuri.org/">
      <Title>{$title}</Title>
      <FromDate>{$fromDate}</FromDate>
      <ToDate>{$toDate}</ToDate>
      <PersonnelCode>{$personnelCode}</PersonnelCode>
    </GetPersonnelDataByDate>
  </soap:Body>
</soap:Envelope>
XML;
    }
}
