<?php


namespace Modules\TripExpense\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DriverAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'exists:users,id'],
            'truck_id' => ['required', 'exists:trucks,id'],
            'start_date' => ['required', 'string'],
            'end_date' => ['nullable', 'string'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    protected function prepareForValidation()
    {
        // تبدیل تاریخ شمسی به میلادی
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

    public function messages(): array
    {
        return [
            'user_id.required' => 'انتخاب راننده الزامی است',
            'user_id.exists' => 'راننده انتخاب شده معتبر نیست',
            'truck_id.required' => 'انتخاب کامیون الزامی است',
            'truck_id.exists' => 'کامیون انتخاب شده معتبر نیست',
            'start_date.required' => 'تاریخ شروع الزامی است',
        ];
    }
}
