<?php
namespace Modules\Assessment\App\Services;

use Modules\Assessment\App\Models\AssessmentCategory;
use Modules\Assessment\App\Models\AssessmentPost;
use Modules\Assessment\App\Models\AssessmentQuestion;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ExcelProfileImporter
{
    private const KNOWN_GRADES = [
        'معاون',
        'مدیر',
        'رئیس',
        'سرپرست/کارشناس ارشد',
        'سرپرست',
        'کارشناس ارشد',
        'کارشناس',
        'کاردان/تکنسین/مسئول',
        'کاردان',
        'تکنسین',
        'مسئول',
        'متصدی',
        'راننده/اپراتور',
        'راننده',
        'اپراتور',
        'کارگر',
    ];

    private const SKIP_SHEETS = ['ref', 'sheet1', 'راهنما', 'guide', 'sheet'];

    private const CATEGORY_SYNONYMS = [
        'فرهنگ سازی' => 'فرهنگ سازمانی',
        'فرهنگ سازمانی و فرهنگ سازی' => 'فرهنگ سازمانی',
        'مدیریت عملکرد افراد، فرآیندها و کمیته ها' => 'فرآیندها و کمیته ها',
        'مدیریت عملکرد افراد, فرآیندها و کمیته ها' => 'فرآیندها و کمیته ها',
        'فرآیندها و کمیته‌ها' => 'فرآیندها و کمیته ها',
        'تغییرات' => 'مدیریت تغییر و انعطاف پذیری',
        'مدیریت تغییر و انعطاف پذیری' => 'مدیریت تغییر و انعطاف‌پذیری',
        'ملاحظات محیط زیستی' => 'ملاحظات محیط زیستی و مدیریت انرژی',
        'ملاحظات مدیریت انرژی' => 'ملاحظات محیط زیستی و مدیریت انرژی',
        'شایستگی های روانشناختی' => 'شایستگی های روانشناختی',
        'شایستگی های  روانشناختی' => 'شایستگی های روانشناختی',
        'روانشناختی' => 'شایستگی های روانشناختی',
        'وظایف و مسئولیتها' => 'وظایف و مسئولیت‌ها',
        'اهداف و استراتژیها' => 'اهداف و استراتژی‌ها',
        'ریسک ها و فرصتها' => 'ریسک ها و فرصت ها',
        'الزامات قانونی و سایر الزامات' => 'الزامات قانونی و سایر الزامات',
        'توسعه و فناوری های نوین' => 'توسعه و فناوری‌های نوین',
        'ایمنی و سلامت شغلی' => 'ایمنی و سلامت شغلی',
        'نیازها و انتظارات ذینفعان' => 'نیازها و انتظارات ذینفعان',
    ];

    private array $anomalies = [];
    private array $stats = [
        'files' => 0,
        'sheets' => 0,
        'questions' => 0,
        'created' => 0,
        'updated' => 0,
        'posts' => 0,
    ];

    /**
     * import یک فایل اکسل
     *
     * ساختار:
     * - نام فایل: {grade}-{unit}.xlsx
     * - هر شیت = یک AssessmentPost جداگانه
     * - domain از نام شیت استخراج می‌شود (بدون grade)
     */
    public function importFile(string $path, bool $dryRun = false, ?string $originalFilename = null): array
    {
        // ریست کردن state
        $this->anomalies = [];
        $this->stats = [
            'files' => 0, 'sheets' => 0, 'questions' => 0,
            'created' => 0, 'updated' => 0, 'posts' => 0,
        ];

        $this->stats['files']++;

        // استفاده از نام اصلی فایل
        $fileName = $originalFilename
            ? pathinfo($originalFilename, PATHINFO_FILENAME)
            : pathinfo($path, PATHINFO_FILENAME);
        $fileName = $this->safeUtf8($fileName);

        // استخراج grade و unit از نام فایل
        $parsed = $this->parseFileName($fileName);
        if (!$parsed) {
            $this->anomalies[] = $this->safeUtf8("فایل {$fileName}: فرمت نام نامعتبر (باید grade-unit باشد)");
            return $this->getResult();
        }

        $grade = $parsed['grade'];
        $unit = $parsed['unit'];

        $spreadsheet = IOFactory::load($path);

        // ✅ پردازش هر شیت به صورت جداگانه
        foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
            $sheetName = $this->safeUtf8(trim($worksheet->getTitle()));

            if (in_array(mb_strtolower($sheetName), self::SKIP_SHEETS)) {
                continue;
            }

            $rows = array_map('array_values', $worksheet->toArray(null, true, true, true));
            $this->stats['sheets']++;

            // ✅ استخراج domain از نام شیت (حذف grade از ابتدا)
            $domain = $this->extractDomainFromSheetName($sheetName, $grade);

            // ✅ ساخت AssessmentPost جداگانه برای هر شیت
            $post = null;
            if (!$dryRun) {
                $postTitle = $this->safeUtf8("{$grade} {$domain} - {$unit}");
                $post = AssessmentPost::updateOrCreate(
                    [
                        'grade' => $grade,
                        'unit' => $unit,
                        'domain' => $domain,
                    ],
                    [
                        'title' => $postTitle,
                        'is_active' => true,
                        'source' => $this->safeUtf8("excel:{$fileName}/{$sheetName}"),
                    ]
                );
                $this->stats['posts']++;
            }

            $this->importSheet($rows, $grade, $unit, $domain, $post, $fileName, $sheetName, $dryRun);
        }

        return $this->getResult();
    }

    /**
     * ✅ متد جدید: استخراج domain از نام شیت
     * مثال: "رئیس حمل و نقل سبک" + grade="رئیس" → "حمل و نقل سبک"
     */
    private function extractDomainFromSheetName(string $sheetName, string $grade): string
    {
        $domain = $sheetName;

        // حذف grade از ابتدای نام شیت
        if (mb_strpos($domain, $grade) === 0) {
            $domain = mb_substr($domain, mb_strlen($grade));
        }

        // نرمال‌سازی و حذف کاراکترهای اضافی
        $domain = $this->normalizeText($domain);
        $domain = ltrim($domain, ' -');

        return $domain ?: $sheetName;
    }

    /**
     * استخراج grade و unit از نام فایل
     */
    private function parseFileName(string $fileName): ?array
    {
        $fileName = pathinfo($fileName, PATHINFO_FILENAME);

        if (!str_contains($fileName, '-')) {
            return null;
        }

        $parts = explode('-', $fileName, 2);
        $gradeRaw = $this->normalizeText($parts[0]);
        $unitRaw = $this->normalizeText($parts[1] ?? '');

        if (empty($gradeRaw) || empty($unitRaw)) {
            return null;
        }

        $grade = $this->resolveGrade($gradeRaw);
        if (!$grade) {
            return null;
        }

        return ['grade' => $grade, 'unit' => $unitRaw];
    }

    /**
     * تطبیق grade با لیست معتبر
     */
    private function resolveGrade(string $raw): ?string
    {
        if (in_array($raw, self::KNOWN_GRADES)) {
            return $raw;
        }

        foreach (self::KNOWN_GRADES as $grade) {
            if (mb_strpos($raw, $grade) !== false) {
                return $grade;
            }
        }

        return null;
    }

    /**
     * import سوالات یک شیت
     */
    private function importSheet(
        array $rows,
        string $grade,
        string $unit,
        string $domain,
        ?AssessmentPost $post,
        string $fileName,
        string $sheetName,
        bool $dryRun
    ): void {
        $currentCategory = null;

        foreach ($rows as $row) {$rowNum = trim((string) ($row[0] ?? ''));

            $rawTitle = (string) ($row[2] ?? '');

            if (is_numeric($rowNum)) {
                \Log::info('EXCEL_TITLE_DEBUG', [
                    'row' => $rowNum,
                    'title' => $rawTitle,
                    'valid_utf8' => mb_check_encoding($rawTitle, 'UTF-8'),
                    'length' => strlen($rawTitle),
                    'hex' => bin2hex($rawTitle),
                ]);
            }

            $catRaw = $this->normalizeText((string) ($row[1] ?? ''));
            $titleRaw = $this->cleanTitle($rawTitle);
            $scoreRaw = trim((string) ($row[3] ?? ''));

            $scoreIsNumeric = is_numeric($scoreRaw) && (int) $scoreRaw >= 1 && (int) $scoreRaw <= 5;
            $rowNumIsNumeric = is_numeric($rowNum);

            if ($titleRaw === '' || $this->isBlacklisted($titleRaw)) {
                continue;
            }
            if (!$scoreIsNumeric && !$rowNumIsNumeric) {
                continue;
            }

            if ($catRaw !== '' && !$this->isBlacklisted($catRaw)) {
                $currentCategory = $this->canonicalCategory($catRaw);
            }

            $score = $scoreIsNumeric ? (int) $scoreRaw : null;
            $this->stats['questions']++;

            if ($dryRun) {
                continue;
            }

            try {
                $category = AssessmentCategory::firstOrCreate(
                    ['title' => $currentCategory ?? 'سایر'],
                    ['sort_order' => 99]
                );
                \Log::info('QUESTION_DB_CONNECTION_DEBUG', [
                    'connection' => (new AssessmentQuestion)->getConnectionName(),
                    'database' => (new AssessmentQuestion)->getConnection()->getDatabaseName(),
                    'charset' => \DB::selectOne("
        SELECT @@character_set_client AS client_charset,
               @@character_set_connection AS connection_charset,
               @@character_set_results AS result_charset,
               @@collation_connection AS collation
    "),
                    'title_valid_utf8' => mb_check_encoding($titleRaw, 'UTF-8'),
                    'title_length' => strlen($titleRaw),
                    'title_hex' => bin2hex($titleRaw),
                ]);
                AssessmentQuestion::updateOrCreate(
                    [
                        'post_id' => $post->id,
                        'category_id' => $category->id,
                        'title' => $titleRaw,
                    ],
                    [
                        'required_score' => $score,
                        'risk_level' => $score,
                        'fix_deadline' => null,
                        'is_active' => true,
                    ]
                );

                $this->stats['created']++;
            } catch (\Throwable $e) {
                $errorMsg = $this->safeUtf8($e->getMessage());
                $this->anomalies[] = $this->safeUtf8("فایل {$fileName} / شیت {$sheetName}: خطا در «{$titleRaw}» — {$errorMsg}");
            }
        }
    }

    /**
     * تبدیل امن رشته به UTF-8 معتبر
     */
    private function safeUtf8(?string $text): string
    {
        if ($text === null || $text === '') return '';

        $cleaned = @iconv('UTF-8', 'UTF-8//IGNORE', $text);
        if ($cleaned === false) {
            $cleaned = '';
        }

        if (!mb_check_encoding($cleaned, 'UTF-8')) {
            $cleaned = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        }

        $cleaned = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $cleaned);

        return trim($cleaned);
    }

    private function normalizeText(string $text): string
    {
        $text = $this->safeUtf8($text);

        // حذف BOM و کاراکترهای نامرئی غیرضروری
        $text = str_replace(['‏', '‎'], '', $text);

        // یکسان‌سازی فاصله‌های متوالی
        $text = preg_replace('/[ \t]+/u', ' ', trim($text));

        return $text;
    }

    private function cleanTitle(string $text): string
    {
        $text = $this->normalizeText($text);

        // حذف پیشوند «شایستگی:» به صورت Unicode-safe
        $text = preg_replace('/^\s*شایستگی\s*:\s*/u', '', $text);

        // حذف فاصله و علائم نگارشی فقط از ابتدا و انتهای متن
        // بدون استفاده از trim با character mask بایتی
        $text = preg_replace('/^[\s\.\،,:؛;]+|[\s\.\،,:؛;]+$/u', '', $text);

        return trim($text);
    }

    private function canonicalCategory(string $cat): string
    {
        return self::CATEGORY_SYNONYMS[$cat] ?? $cat;
    }

    private function isBlacklisted(string $value): bool
    {
        foreach (['ردیف', 'منظر شایستگی', 'عناوین شایستگی', 'بخش شناسایی', 'رده شغلی', 'امتیاز دهی', 'پست', 'شاغل'] as $kw) {
            if (str_contains($value, $kw)) return true;
        }
        return false;
    }

    private function getResult(): array
    {
        return [
            'stats' => $this->stats,
            'anomalies' => array_map([$this, 'safeUtf8'], $this->anomalies),
        ];
    }

    /**
     * ✅ متد جدید: تبدیل امن رشته به UTF-8 معتبر
     * استفاده از iconv و mb_convert_encoding به جای پارس دستی
     */
    private function safeConvert(?string $text): string
    {
        if ($text === null || $text === '') return '';

        // روش ۱: اگر قبلاً UTF-8 معتبر است، فقط کاراکترهای کنترلی را حذف کن
        if (mb_check_encoding($text, 'UTF-8')) {
            return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text);
        }

        // روش : تلاش برای تبدیل از Windows-1256 (encoding رایج فارسی در ویندوز)
        $converted = @iconv('Windows-1256', 'UTF-8//IGNORE', $text);
        if ($converted !== false && mb_check_encoding($converted, 'UTF-8')) {
            return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $converted);
        }

        // روش ۳: تلاش برای تبدیل از ISO-8859-6 (encoding عربی/فارسی)
        $converted = @iconv('ISO-8859-6', 'UTF-8//IGNORE', $text);
        if ($converted !== false && mb_check_encoding($converted, 'UTF-8')) {
            return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $converted);
        }

        // روش ۴: استفاده از mb_convert_encoding به عنوان fallback
        $converted = @mb_convert_encoding($text, 'UTF-8', 'auto');
        if (mb_check_encoding($converted, 'UTF-8')) {
            return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $converted);
        }

        // روش ۵: اگر هیچ‌کدام کار نکرد، فقط بایت‌های غیر ASCII را حذف کن
        return preg_replace('/[^\x20-\x7E\x{0600}-\x{06FF}\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]/u', '', $text);
    }
}
