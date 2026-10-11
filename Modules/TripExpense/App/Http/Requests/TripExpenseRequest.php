<?php

namespace Modules\TripExpense\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TripExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => ['required', 'exists:expense_categories,id'],
            'amount' => ['required', 'numeric', 'min:0', 'max:999999999.99'],
            'receipt' => [
                'required',
                'file',
                'mimes:jpg,jpeg,png,pdf',
                'max:2048',
                'mimetypes:image/jpeg,image/png,application/pdf',
            ],
            'expense_date' => ['required', 'string'], // ✅ اصلاح
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'category_id.required' => 'انتخاب دسته‌بندی الزامی است',
            'category_id.exists' => 'دسته‌بندی انتخاب شده معتبر نیست',
            'amount.required' => 'مبلغ هزینه الزامی است',
            'amount.numeric' => 'مبلغ هزینه باید عدد باشد',
            'amount.min' => 'مبلغ هزینه باید بزرگتر از صفر باشد',
            'receipt.required' => 'آپلود تصویر رسید الزامی است',
            'receipt.file' => 'فایل رسید معتبر نیست',
            'receipt.mimes' => 'فرمت فایل رسید باید jpg، png یا pdf باشد',
            'receipt.max' => 'حجم فایل رسید نباید بیشتر از 2 مگابایت باشد',
            'expense_date.required' => 'تاریخ هزینه الزامی است',
        ];
    }

    protected function prepareForValidation()
    {
        if ($this->has('expense_date')) {
            $this->merge([
                'expense_date' => $this->convertJalaliToGregorian($this->expense_date),
            ]);
        }
    }

    private function convertJalaliToGregorian($date): ?string
    {
        if (empty($date)) return null;
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) return $date;

        $date = $this->convertPersianNumbers($date);

        try {
            $jalali = \Morilog\Jalali\Jalalian::fromFormat('Y/m/d', $date);
            return $jalali->toCarbon()->format('Y-m-d');
        } catch (\Exception $e) {
            return $date;
        }
    }

    private function convertPersianNumbers($string): string
    {
        $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $arabic = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $english = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

        $string = str_replace($persian, $english, $string);
        return str_replace($arabic, $english, $string);
    }
}
