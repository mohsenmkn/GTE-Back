<?php

namespace Modules\PettyCash\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePettyCashRequest extends FormRequest
{
    public function authorize() { return true; }

    public function rules()
    {
        return [
            'project_id'      => 'required|exists:projects,id',
            'name'            => 'nullable|string|max:255',
            'initial_amount'  => 'required|numeric|min:0',
            'current_balance' => 'sometimes|numeric|min:0',
            'is_active'       => 'sometimes|boolean',
        ];
    }
}
