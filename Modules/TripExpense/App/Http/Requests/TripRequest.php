<?php

namespace Modules\TripExpense\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TripRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'driver_id' => ['required', 'exists:users,id'],
            'truck_id' => ['required', 'exists:trucks,id'],
            'origin' => ['required', 'string', 'max:200'],
            'destination' => ['required', 'string', 'max:200'],
            'start_date' => ['required', 'string'], // ✅ اصلاح
            'end_date' => ['nullable', 'string'], // ✅ اصلاح
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'driver_id.required' => 'انتخاب راننده الزامی است',
            'driver_id.exists' => 'راننده انتخاب شده معتبر نیست',
            'truck_id.required' => 'انتخاب کامیون الزامی است',
            'truck_id.exists' => 'کامیون انتخاب شده معتبر نیست',
            'origin.required' => 'مبدأ الزامی است',
            'destination.required' => 'مقصد الزامی است',
            'start_date.required' => 'تاریخ شروع سفر الزامی است',
        ];
    }

    protected function prepareForValidation()
    {
        if ($this->has('start_date')) {
            $this->merge([
                'start_date' => $this->convertJalaliToGregorian($this->start_date),
            ]);
        }

        if ($this->filled('end_date')) {
            $this->merge([
                'end_date' => $this->convertJalaliToGregorian($this->end_date),
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
