<?php
// Modules/Company/Http/Requests/StoreCompanyRequest.php

namespace Modules\Company\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'                => 'required|string|max:255',
            'code'                => 'required|string|max:50|unique:companies,code',
            'registration_number' => 'nullable|string|max:100',
            'tax_number'          => 'nullable|string|max:100',
            'address'             => 'nullable|string',
            'phone'               => 'nullable|string|max:20',
            'email'               => 'nullable|email|max:255',
            'website'             => 'nullable|url|max:255',
            'logo'                => 'nullable|image|max:2048',
            'is_active'           => 'boolean',
        ];
    }
}
