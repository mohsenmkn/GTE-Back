<?php
// Modules/Company/Transformers/CompanyResource.php

namespace Modules\Company\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;

class CompanyResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                  => $this->id,
            'name'                => $this->name,
            'code'                => $this->code,
            'registration_number' => $this->registration_number,
            'tax_number'          => $this->tax_number,
            'address'             => $this->address,
            'phone'               => $this->phone,
            'email'               => $this->email,
            'website'             => $this->website,
            'logo'                => $this->logo,
            'is_active'           => $this->is_active,
            'projects_count'      => $this->whenCounted('projects'),
            'created_at'          => $this->created_at->toDateTimeString(),
        ];
    }
}

