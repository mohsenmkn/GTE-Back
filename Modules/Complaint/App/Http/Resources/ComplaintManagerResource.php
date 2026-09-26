<?php

namespace Modules\Complaint\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ComplaintManagerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organizational_unit' => [
                'id' => $this->organizationalUnit->id,
                'title' => $this->organizationalUnit->title,
                'breadcrumb' => $this->organizationalUnit->breadcrumb,
            ],
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'mobile' => $this->user->mobile,
                'personnel_code' => $this->user->personnel_code,
            ],
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
