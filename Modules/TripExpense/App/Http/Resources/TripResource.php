<?php

namespace Modules\TripExpense\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TripResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'driver_id' => $this->driver_id,
            'driver' => $this->whenLoaded('driver', fn () => [
                'id' => $this->driver->id,
                'name' => $this->driver->name,
                'personnel_code' => $this->driver->personnel_code ?? null,
            ]),
            'truck_id' => $this->truck_id,
            'truck' => $this->whenLoaded('truck', fn () => [
                'id' => $this->truck->id,
                'code' => $this->truck->code,
                'plate_number' => $this->truck->plate_number,
            ]),
            'origin' => $this->origin,
            'destination' => $this->destination,
            'start_date' => $this->start_date?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            'status' => $this->status,
            'status_label' => $this->getStatusLabels()[$this->status] ?? $this->status,
            'description' => $this->description,
            'total_expenses' => (float) $this->total_expenses,
            'approved_expenses' => (float) $this->approved_expenses,
            'expenses_count' => $this->whenLoaded('expenses', fn () => $this->expenses->count()),
            'expenses' => TripExpenseResource::collection($this->whenLoaded('expenses')),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }

    private function getStatusLabels(): array
    {
        return [
            'pending' => 'در انتظار شروع',
            'in_progress' => 'در حال انجام',
            'completed' => 'پایان یافته',
            'cancelled' => 'لغو شده',
        ];
    }
}
