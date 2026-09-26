<?php


namespace Modules\WarehouseGtrabar\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PartInstallationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'equipment_id' => $this->equipment_id,
            'equipment' => new EquipmentResource($this->whenLoaded('equipment')),
            'part_code' => $this->part_code,
            'part_name' => $this->part_name,
            'part_sql_server_id' => $this->part_sql_server_id,
            'installed_at' => $this->installed_at?->format('Y-m-d H:i:s'),
            'removed_at' => $this->removed_at?->format('Y-m-d H:i:s'),
            'installation_location' => $this->installation_location,
            'serial_number' => $this->serial_number,
            'notes' => $this->notes,
            'is_active' => is_null($this->removed_at),
            'installed_by' => $this->whenLoaded('installer', fn() => [
                'id' => $this->installer->id,
                'name' => $this->installer->name,
            ]),
            'removed_by' => $this->whenLoaded('remover', fn() => [
                'id' => $this->remover->id,
                'name' => $this->remover->name,
            ]),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
