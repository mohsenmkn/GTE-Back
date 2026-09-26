<?php

// Modules/Project/Http/Requests/StoreProjectRequest.php

namespace Modules\Project\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id'   => 'required|exists:companies,id',
            'code'         => 'required|string|max:50|unique:projects,code',
            'name'         => 'required|string|max:255',
            'type'         => 'required|in:residential,commercial,industrial',
            'location'     => 'nullable|string|max:255',
            'start_date'   => 'nullable|date',
            'end_date'     => 'nullable|date|after_or_equal:start_date',
            'total_budget' => 'nullable|numeric|min:0',
            'status'       => 'required|in:planning,active,on_hold,completed,cancelled',
            'manager_id'   => 'nullable|exists:users,id',
            'description'  => 'nullable|string',
        ];
    }
    public function messages(): array
    {
        return [
            'company_id.required'   => 'انتخاب شرکت الزامی است',
            'company_id.exists'     => 'شرکت انتخابی معتبر نیست',
            'code.required'         => 'کد پروژه الزامی است',
            'code.unique'           => 'این کد قبلاً برای پروژه دیگری استفاده شده',
            'code.max'              => 'کد پروژه نباید بیشتر از ۵۰ کاراکتر باشد',
            'name.required'         => 'نام پروژه الزامی است',
            'type.required'         => 'نوع پروژه الزامی است',
            'type.in'               => 'نوع پروژه انتخابی معتبر نیست',
            'end_date.after_or_equal' => 'تاریخ پایان باید بعد از تاریخ شروع باشد',
            'total_budget.numeric'  => 'بودجه باید عدد باشد',
            'total_budget.min'      => 'بودجه نمی‌تواند منفی باشد',
            'status.required'       => 'وضعیت پروژه الزامی است',
            'status.in'             => 'وضعیت انتخابی معتبر نیست',
            'manager_id.exists'     => 'مدیر انتخابی معتبر نیست',
        ];
    }
}
