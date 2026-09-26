<?php

namespace Modules\Assessment\App\Services;

class JobFamilyClassifier
{
    /**
     * ۹ خانواده شغلی نهایی
     */
    public const FAMILIES = [
        'معاون',
        'مدیر',
        'رئیس',
        'سرپرست/کارشناس ارشد',
        'کارشناس',
        'کاردان/تکنسین/مسئول',
        'راننده/اپراتور',
        'متصدی',
        'کارگر',
    ];

    /**
     * ترتیب بررسی مهم است: اولین تطابق برنده است
     */
    private const RULES = [
        'معاون'                => ['معاون'],
        'مدیر'                 => ['مدیر'],
        'رئیس'                 => ['رئیس', 'رییس'],
        'سرپرست/کارشناس ارشد'  => ['سرپرست', 'کارشناس ارشد'],
        'کارشناس'              => ['کارشناس'],
        'کاردان/تکنسین/مسئول'  => ['کاردان', 'تکنسین', 'مسئول'],
        'راننده/اپراتور'       => ['راننده', 'اپراتور'],
        'متصدی'                => ['متصدی'],
        'کارگر'                => ['کارگر', 'کمک مکانیک', 'کمک برقکار', 'کمک انباردار'],
    ];

    public function classify(?string $postTitle): ?string
    {
        if (!$postTitle) {
            return null;
        }

        foreach (self::RULES as $family => $keywords) {
            foreach ($keywords as $keyword) {
                if (mb_strpos($postTitle, $keyword) !== false) {
                    return $family;
                }
            }
        }

        return null;
    }

    public function isAssessable(?string $postTitle): bool
    {
        return $this->classify($postTitle) !== null;
    }
}
