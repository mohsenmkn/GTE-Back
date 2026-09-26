<?php
// Modules/Contract/App/Http/Requests/StoreContractorRequest.php

namespace Modules\Contract\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreContractorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id' => 'required|exists:companies,id',
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50|unique:contractors',
            'registration_number' => 'nullable|string|max:50',
            'economic_code' => 'nullable|string|max:50',
            'national_id' => 'nullable|string|max:20',
            'phone' => 'nullable|string|max:20',
            'mobile' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'website' => 'nullable|url|max:255',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'province' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'bank_name' => 'nullable|string|max:100',
            'bank_account_number' => 'nullable|string|max:50',
            'bank_card_number' => 'nullable|string|max:50',
            'shaba_number' => 'nullable|string|max:50',
            'type' => 'required|in:legal,real',
            'expertise' => 'nullable|array',
            'certificates' => 'nullable|array',
            'description' => 'nullable|string',
            'status' => 'required|in:active,inactive,suspended',
        ];
    }

    public function messages(): array
    {
        return [
            'company_id.required' => 'انتخاب شرکت الزامی است.',
            'name.required' => 'نام پیمانکار الزامی است.',
            'type.required' => 'نوع پیمانکار (حقوقی/حقیقی) الزامی است.',
            'status.required' => 'وضعیت پیمانکار الزامی است.',
        ];
    }
}
