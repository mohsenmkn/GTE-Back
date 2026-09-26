<?php


namespace Modules\PreWarehouse\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('pre_warehouse.location.assign');
    }

    public function rules(): array
    {
        return [
            'warehouse_location_id' => 'nullable|exists:warehouse_locations,id',
            'location_name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'section_code' => 'nullable|string|max:50',
            'assigned_qty' => 'required|integer|min:1',
        ];
    }

    public function messages(): array
    {
        return [
            'location_name.required' => 'نام محل نگهداری الزامی است',
            'assigned_qty.required' => 'مقدار تخصیص‌یافته الزامی است',
        ];
    }
}
