<?php


namespace Modules\PreWarehouse\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AllocateWarehousesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('pre_warehouse.warehouse.allocate');
    }

    public function rules(): array
    {
        return [
            'allocations' => 'required|array|min:1',
            'allocations.*.warehouse_id' => 'required|exists:warehouses,id',
            'allocations.*.allocated_qty' => 'required|integer|min:1',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            if (!$this->has('allocations')) {
                return;
            }

            $totalAllocated = 0;
            foreach ($this->input('allocations') as $allocation) {
                $totalAllocated += $allocation['allocated_qty'] ?? 0;
            }

            // Get purchase quantity
            $purchase = \Modules\PreWarehouse\App\Models\Purchase::findOrFail($this->route('id'));

            if ($totalAllocated !== $purchase->quantity) {
                $validator->errors()->add(
                    'allocations',
                    "مجموع مقادیر تخصیص‌یافته ({$totalAllocated}) باید برابر با مقدار کل خرید ({$purchase->quantity}) باشد"
                );
            }
        });
    }
}
