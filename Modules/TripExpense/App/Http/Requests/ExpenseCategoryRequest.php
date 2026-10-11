<?php

namespace Modules\TripExpense\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExpenseCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $categoryId = $this->route('expense_category')?->id;

        return [
            'name' => ['required', 'string', 'max:100'],
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('expense_categories', 'code')->ignore($categoryId),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'نام دسته‌بندی الزامی است',
            'code.required' => 'کد دسته‌بندی الزامی است',
            'code.unique' => 'این کد دسته‌بندی قبلاً ثبت شده است',
        ];
    }
}
