<?php
// Modules/Contract/App/Http/Requests/StoreContractRequest.php

namespace Modules\Contract\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'project_id' => 'required|exists:projects,id',
            'contractor_id' => 'required|exists:contractors,id',
            'contract_number' => 'nullable|string|max:50|unique:contracts',
            'title' => 'required|string|max:255',
            'type' => 'required|in:construction,service,consulting,supply,other',
            'amount' => 'required|numeric|min:0',
            'paid_amount' => 'nullable|numeric|min:0',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'status' => 'required|in:draft,pending,active,completed,cancelled,suspended',
            'description' => 'nullable|string',
            'terms' => 'nullable|string',
            'wbs_items' => 'nullable|array',
            'wbs_items.*.id' => 'required|exists:wbs_items,id',
            'wbs_items.*.quantity' => 'nullable|numeric|min:0',
            'wbs_items.*.unit_price' => 'nullable|numeric|min:0',
            'wbs_items.*.description' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'company_id.required' => 'انتخاب شرکت الزامی است.',
            'project_id.required' => 'انتخاب پروژه الزامی است.',
            'contractor_id.required' => 'انتخاب پیمانکار الزامی است.',
            'title.required' => 'عنوان قرارداد الزامی است.',
            'type.required' => 'نوع قرارداد الزامی است.',
            'amount.required' => 'مبلغ قرارداد الزامی است.',
            'amount.min' => 'مبلغ قرارداد باید بیشتر از صفر باشد.',
            'status.required' => 'وضعیت قرارداد الزامی است.',
        ];
    }
}
