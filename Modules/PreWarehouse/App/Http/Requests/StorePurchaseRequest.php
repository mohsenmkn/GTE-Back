<?php

namespace Modules\PreWarehouse\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('pre_warehouse.commercial.create');
    }

    public function rules(): array
    {
        return [
            'item_name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'quantity' => 'required|integer|min:1',
            'unit_of_measurement' => 'required|string|max:50',
            'target_unit_id' => 'nullable|exists:organizational_units,id',
            'metadata' => 'nullable|array',
            'metadata.invoice_number' => 'nullable|string|max:100',
            'metadata.purchase_date' => 'nullable|date',
            'metadata.supplier' => 'nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'item_name.required' => 'نام کالا الزامی است',
            'quantity.required' => 'مقدار کالا الزامی است',
            'quantity.min' => 'مقدار کالا باید حداقل 1 باشد',
            'unit_of_measurement.required' => 'واحد اندازه‌گیری الزامی است',
        ];
    }
}
