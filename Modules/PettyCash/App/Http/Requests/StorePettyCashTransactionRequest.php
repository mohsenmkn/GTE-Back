<?php

namespace Modules\PettyCash\App\Http\Requests;



use Illuminate\Foundation\Http\FormRequest;

class StorePettyCashTransactionRequest extends FormRequest
{
    public function authorize() { return true; }

    public function rules()
    {
        return [
            'wbs_item_id'      => 'nullable|exists:wbs_items,id',
            'type'             => 'required|in:expense,deposit,adjustment',
            'amount'           => 'required|numeric|min:0.01',
            'description'      => 'required|string|max:500',
            'transaction_date' => 'required|date',
            'reference_number' => 'nullable|string|max:100',
        ];
    }
}
