<?php

namespace Modules\WBS\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreWbsItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'project_id'  => 'required|exists:projects,id',
            'parent_id'   => 'nullable|exists:wbs_items,id',
            'code'        => 'required|string|max:50',
            'name'        => 'required|string|max:255',
            'category'    => 'required|in:civil,electrical,mechanical',
            'unit'        => 'nullable|string|max:50',
            'quantity'    => 'nullable|numeric|min:0',
            'unit_price'  => 'nullable|numeric|min:0',
            'weight'      => 'nullable|numeric|min:0|max:100',
            'status'      => 'required|in:pending,in_progress,completed,on_hold', // ✅ اصلاح
            'description' => 'nullable|string',
        ];
    }

    // ✅ اضافه کردن متد prepareForValidation برای تبدیل parent_id
    protected function prepareForValidation(): void
    {
        if ($this->has('parent_id') && ($this->parent_id === '' || $this->parent_id === 'null')) {
            $this->merge(['parent_id' => null]);
        }
    }
}
