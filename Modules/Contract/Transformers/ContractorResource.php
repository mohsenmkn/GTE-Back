<?php
// Modules/Contract/Transformers/ContractorResource.php

namespace Modules\Contract\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;

class ContractorResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'name' => $this->name,
            'code' => $this->code,
            'registration_number' => $this->registration_number,
            'economic_code' => $this->economic_code,
            'national_id' => $this->national_id,
            'phone' => $this->phone,
            'mobile' => $this->mobile,
            'email' => $this->email,
            'website' => $this->website,
            'address' => $this->address,
            'city' => $this->city,
            'province' => $this->province,
            'postal_code' => $this->postal_code,
            'full_address' => $this->full_address,
            'bank_name' => $this->bank_name,
            'bank_account_number' => $this->bank_account_number,
            'bank_card_number' => $this->bank_card_number,
            'shaba_number' => $this->shaba_number,
            'type' => $this->type,
            'type_label' => $this->type_label,
            'expertise' => $this->expertise,
            'certificates' => $this->certificates,
            'description' => $this->description,
            'status' => $this->status,
            'status_label' => $this->status_label,
            'status_severity' => $this->status_severity,
            'contracts_count' => $this->whenCounted('contracts'),
            'created_by' => $this->creator?->name,
            'created_at' => $this->created_at?->toDateString(),
            'updated_at' => $this->updated_at?->toDateString(),
        ];
    }
}
