<?php

namespace Modules\TripExpense\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DriverAssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'truck_id' => $this->truck_id,
            'driver' => $this->whenLoaded('driver', fn () => [
                'id' => $this->driver->id,
                'name' => $this->driver->name,
                'personnel_code' => $this->driver->personnel_code,
                'mobile' => $this->driver->mobile,
            ]),
            'truck' => $this->whenLoaded('truck', fn () => [
                'id' => $this->truck->id,
                'code' => $this->truck->code,
                'plate_number' => $this->truck->plate_number,
                'brand' => $this->truck->brand,
                'model' => $this->truck->model,
            ]),
            'start_date' => $this->start_date?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            'is_active' => $this->isActive(),
            'status_label' => $this->isActive() ? 'فعال' : 'پایان یافته',
            'duration_days' => $this->start_date?->diffInDays($this->end_date ?? now()),
            'note' => $this->note,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
