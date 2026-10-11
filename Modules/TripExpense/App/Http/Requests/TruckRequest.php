<?php

namespace Modules\TripExpense\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TruckRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $truckId = $this->route('truck')?->id;

        return [
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('trucks', 'code')->ignore($truckId),
            ],
            'plate_number' => ['required', 'string', 'max:20'],
            'brand' => ['nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:active,inactive,maintenance'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'کد کامیون الزامی است',
            'code.unique' => 'این کد کامیون قبلاً ثبت شده است',
            'plate_number.required' => 'پلاک کامیون الزامی است',
            'status.in' => 'وضعیت کامیون معتبر نیست',
        ];
    }
}
