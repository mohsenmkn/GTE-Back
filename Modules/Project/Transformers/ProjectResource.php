<?php
// Modules/Project/Transformers/ProjectResource.php

namespace Modules\Project\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'           => $this->id,
            'company_id'   => $this->company_id,
            'company_name' => $this->whenLoaded('company', fn() => $this->company->name),
            'code'         => $this->code,
            'name'         => $this->name,
            'type'         => $this->type,
            'location'     => $this->location,
            'start_date'   => $this->start_date?->format('Y-m-d'),
            'end_date'     => $this->end_date?->format('Y-m-d'),
            'total_budget' => $this->total_budget,
            'status'       => $this->status,
            'manager_id'   => $this->manager_id,
            'manager_name' => $this->whenLoaded('manager', fn() => $this->manager?->name),
            'description'  => $this->description,
            'created_at'   => $this->created_at->format('Y-m-d'),];
    }
}
