<?php

namespace Modules\TripExpense\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExpenseLimitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => ['required', 'exists:expense_categories,id'],
            'limit_type' => ['required', 'in:per_trip,monthly'],
            'amount' => ['required', 'numeric', 'min:0', 'max:999999999.99'],

            // ✅ اصلاح: حذف date_format برای قبول فرمت شمسی
            'effective_from' => ['required', 'string'],
            'effective_to' => ['nullable', 'string'],

            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'category_id.required' => 'انتخاب دسته‌بندی الزامی است',
            'category_id.exists' => 'دسته‌بندی انتخاب شده معتبر نیست',
            'limit_type.required' => 'نوع سقف الزامی است',
            'limit_type.in' => 'نوع سقف باید per_trip یا monthly باشد',
            'amount.required' => 'مبلغ سقف الزامی است',
            'amount.numeric' => 'مبلغ سقف باید عدد باشد',
            'effective_from.required' => 'تاریخ شروع اعتبار الزامی است',
        ];
    }

    /**
     * ✅ تبدیل تاریخ شمسی به میلادی قبل از validation
     */
    protected function prepareForValidation()
    {
        if ($this->has('effective_from')) {
            $this->merge([
                'effective_from' => $this->convertJalaliToGregorian($this->effective_from),
            ]);
        }

        if ($this->filled('effective_to')) {
            $this->merge([
                'effective_to' => $this->convertJalaliToGregorian($this->effective_to),
            ]);
        }
    }

    /**
     * تبدیل تاریخ شمسی به میلادی
     */
    private function convertJalaliToGregorian($date): ?string
    {
        if (empty($date)) return null;

        // اگر فرمت میلادی بود (YYYY-MM-DD)، همان را برگردان
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $date;
        }

        // اگر فرمت شمسی بود (YYYY/MM/DD یا YYYY-MM-DD با اعداد فارسی)
        // تبدیل اعداد فارسی به انگلیسی
        $date = $this->convertPersianNumbers($date);

        try {
            $jalali = \Morilog\Jalali\Jalalian::fromFormat('Y/m/d', $date);
            return $jalali->toCarbon()->format('Y-m-d');
        } catch (\Exception $e) {
            // اگر تبدیل نشد، همان مقدار را برگردان (validation بعداً خطا می‌دهد)
            return $date;
        }
    }

    /**
     * تبدیل اعداد فارسی/عربی به انگلیسی
     */
    private function convertPersianNumbers($string): string
    {
        $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $arabic = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $english = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

        $string = str_replace($persian, $english, $string);
        return str_replace($arabic, $english, $string);
    }
}
