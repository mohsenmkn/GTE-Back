<?php

namespace Modules\PreWarehouse\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LocationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'warehouse_location' => $this->warehouseLocation ? [
                'id' => $this->warehouseLocation->id,
                'name' => $this->warehouseLocation->name,
                'section' => $this->warehouseLocation->section,
            ] : null,
            'location_name' => $this->location_name,
            'description' => $this->description,
            'section_code' => $this->section_code,
            'assigned_qty' => $this->assigned_qty,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
