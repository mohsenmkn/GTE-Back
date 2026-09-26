<?php


namespace Modules\PreWarehouse\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ReceiveAllocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('pre_warehouse.warehouse.receive');
    }

    public function rules(): array
    {
        return [
            'qty' => 'required|integer|min:1',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $allocation = \Modules\PreWarehouse\App\Models\Allocation::findOrFail($this->route('id'));

            if ($this->input('qty') > ($allocation->allocated_qty - $allocation->received_qty)) {
                $validator->errors()->add(
                    'qty',
                    'مقدار دریافتی نمی‌تواند بیشتر از مقدار باقیمانده باشد'
                );
            }
        });
    }
}
