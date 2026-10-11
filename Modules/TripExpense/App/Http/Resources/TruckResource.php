<?php

namespace Modules\TripExpense\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TruckResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'plate_number' => $this->plate_number,
            'brand' => $this->brand,
            'model' => $this->model,
            'status' => $this->status,
            'status_label' => $this->getStatusLabels()[$this->status] ?? $this->status,
            'description' => $this->description,
            'current_drivers_count' => $this->whenLoaded('currentDrivers', fn () => $this->currentDrivers->count()),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }

    private function getStatusLabels(): array
    {
        return [
            'active' => 'فعال',
            'inactive' => 'غیرفعال',
            'maintenance' => 'در حال تعمیر',
        ];
    }
}
