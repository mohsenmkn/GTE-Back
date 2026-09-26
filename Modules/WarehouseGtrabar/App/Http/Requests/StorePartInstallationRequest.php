<?php


namespace Modules\WarehouseGtrabar\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePartInstallationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'equipment_id' => ['required', 'integer', 'exists:equipment,id'],
            'part_code' => ['required', 'string', 'max:64'],
            'part_name' => ['required', 'string', 'max:256'],
            'part_sql_server_id' => ['nullable', 'integer'],
            'installed_at' => ['required', 'date'],
            'installation_location' => ['nullable', 'string', 'max:256'],
            'serial_number' => ['nullable', 'string', 'max:128'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'equipment_id.required' => 'تجهیز الزامی است',
            'equipment_id.exists' => 'تجهیز انتخاب شده معتبر نیست',
            'part_code.required' => 'کد قطعه الزامی است',
            'part_name.required' => 'نام قطعه الزامی است',
            'installed_at.required' => 'تاریخ نصب الزامی است',
        ];
    }
}
