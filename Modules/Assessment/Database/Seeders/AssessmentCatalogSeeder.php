<?php
namespace Modules\Assessment\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Assessment\App\Models\AssessmentCategory;
use Modules\Assessment\App\Models\AssessmentGeneralRisk;
use Modules\Assessment\App\Models\AssessmentMethod;

class AssessmentCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedCategories();
        $this->seedRisks();
        $this->seedMethods();

        $this->command->info('✅ کاتالوگ ارزیابی با موفقیت seed شد.');
    }

    /**
     * مناظر شایستگی (بر اساس فایل‌های اکسل ارسالی)
     */
    private function seedCategories(): void
    {
        $categories = [
            ['title' => 'توانمندی‌های بنیادی ادراکی',       'sort_order' => 1],
            ['title' => 'توانمندی‌های بنیادی جسمی',         'sort_order' => 2],
            ['title' => 'شایستگی‌های روانشناختی',           'sort_order' => 3],
            ['title' => 'وظایف و مسئولیت‌ها',              'sort_order' => 4],
            ['title' => 'دانش فنی و تخصصی',                'sort_order' => 5],
            ['title' => 'اهداف و استراتژی‌ها',             'sort_order' => 6],
            ['title' => 'مدیریت تغییر و انعطاف‌پذیری',      'sort_order' => 7],
            ['title' => 'ریسک‌ها و فرصت‌ها',               'sort_order' => 8],
            ['title' => 'الزامات قانونی و سایر الزامات',    'sort_order' => 9],
            ['title' => 'توسعه و فناوری‌های نوین',          'sort_order' => 10],
            ['title' => 'ملاحظات محیط زیستی و مدیریت انرژی', 'sort_order' => 11],
            ['title' => 'ایمنی و سلامت شغلی',              'sort_order' => 12],
            ['title' => 'فرآیندها و کمیته‌ها',             'sort_order' => 13],
            ['title' => 'فرهنگ سازمانی',                   'sort_order' => 14],
            ['title' => 'نیازها و انتظارات ذینفعان',       'sort_order' => 15],
            ['title' => 'سایر',                            'sort_order' => 99],
        ];

        foreach ($categories as $data) {
            AssessmentCategory::updateOrCreate(
                ['title' => $data['title']],
                ['sort_order' => $data['sort_order'], 'is_active' => true]
            );
        }

        $this->command->info('  ✓ ' . count($categories) . ' منظر شایستگی ثبت شد.');
    }

    /**
     * ریسک‌های عمومی کاستی شایستگی
     */
    private function seedRisks(): void
    {
        $risks = [
            'هدر رفت منابع',
            'کمبود اطلاعات',
            'خطا در مکاتبات',
            'تفسیر نادرست قوانین و الزامات',
            'خطا در برنامه‌ریزی',
            'خطا در اجرای کار',
            'عدم تحویل کار در مهلت مقرر',
            'عدم تحویل کار با کیفیت مقرر',
            'عدم تحویل کار با هزینه مقرر',
            'ارائه گزارش‌های نادرست',
            'استفاده نادرست از ابزارها و تجهیزات',
            'فساد اداری',
            'تنش با همکاران',
            'عدم تحقق اهداف تعریف شده',
            'عدم به‌کارگیری درست سیستم‌ها و رویکردها',
            'عدم به‌کارگیری خلاقیت و نوآوری',
            'توقف در عملیات',
            'عدم برآورده‌سازی نیاز ذینفعان',
            'عدم انطباق در محصول',
            'رخ دادن حوادث ایمنی و بهداشت',
            'آلایندگی محیط زیستی',
            'نارضایتی مشتریان داخلی و خارجی',
            'کاهش بهره‌وری سازمانی',
            'از دست دادن فرصت‌های رقابتی',
            'تخریب شهرت و اعتبار سازمان',
        ];

        foreach ($risks as $title) {
            AssessmentGeneralRisk::firstOrCreate(
                ['title' => $title],
            );
        }

        $this->command->info('  ✓ ' . count($risks) . ' ریسک عمومی ثبت شد.');
    }

    /**
     * روش‌های رفع خلا شایستگی
     */
    private function seedMethods(): void
    {
        $methods = [
            // روش‌های آموزشی کلاسیک
            'مطالعه کتاب و منابع تخصصی',
            'شبیه‌سازی (Simulation)',
            'شرکت در وبینار',
            'دوره آموزشی حضوری',
            'دوره آموزشی الکترونیکی (E-Learning)',
            'کارگاه عملی (Workshop)',
            'مطالعه موردی (Case Study)',
            'ایفای نقش (Role Playing)',
            'سایه‌سازی شغلی (Job Shadowing)',
            'خودآموزی (Self-Study)',

            // روش‌های مبتنی بر تجربه
            'آموزش حین کار (OJT)',
            'چرخش شغلی (Job Rotation)',
            'واگذاری پروژه چالشی',
            'بازدید فنی و صنعتی',
            'مأموریت آموزشی',

            // روش‌های کوچینگ و منتورینگ
            'مربی‌گری (Coaching)',
            'منتورینگ (Mentoring)',
            'بازخورد ۳۶۰ درجه',
            'ارزیابی عملکرد دوره‌ای',

            // روش‌های حرفه‌ای و گواهینامه
            'دریافت گواهینامه حرفه‌ای',
            'شرکت در کنفرانس و سمینار',
            'عضویت در انجمن‌های حرفه‌ای',
            'تحقیق و پژوهش کاربردی',
            'نوشتن مقاله تخصصی',
            'ارائه در همایش‌های علمی',

            // روش‌های رهبری و توسعه
            'تدریس و آموزش به دیگران',
            'راهبری تیم پروژه',
            'جانشین‌پروری',
            'بازنگری در شرح شغل',
            'ارتقاء شغلی',
            'تفویض اختیار تدریجی',

            // روش‌های دیجیتال و نوین
            'استفاده از پلتفرم‌های یادگیری آنلاین',
            'یادگیری مبتنی بر VR/AR',
            'Micro-Learning (یادگیری خرد)',
            'Gamification (بازی‌وارسازی)',
        ];

        foreach ($methods as $title) {
            AssessmentMethod::firstOrCreate(
                ['title' => $title],
                ['is_active' => true]
            );
        }

        $this->command->info('  ✓ ' . count($methods) . ' روش رفع خلا ثبت شد.');
    }
}
