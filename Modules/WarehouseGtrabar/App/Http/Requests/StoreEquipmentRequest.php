<?php


namespace Modules\WarehouseGtrabar\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEquipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:64', 'unique:equipment,code'],
            'title' => ['required', 'string', 'max:256'],
            'title_en' => ['nullable', 'string', 'max:256'],
            'type' => ['nullable', 'integer', 'in:1,2,3,4,5,6,7'],
            'description' => ['nullable', 'string'],
            'state' => ['nullable', 'integer', 'in:1,2,3'],
            'parent_equipment_id' => ['nullable', 'integer', 'exists:equipment,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'کد تجهیز الزامی است',
            'code.unique' => 'این کد تجهیز قبلاً ثبت شده است',
            'title.required' => 'عنوان تجهیز الزامی است',
        ];
    }
}
