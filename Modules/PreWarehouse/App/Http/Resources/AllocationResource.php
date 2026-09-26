<?php

namespace Modules\PreWarehouse\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AllocationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'warehouse' => $this->warehouse ? [
                'id' => $this->warehouse->id,
                'name' => $this->warehouse->name,
                'code' => $this->warehouse->code,
            ] : null,
            'allocated_qty' => $this->allocated_qty,
            'received_qty' => $this->received_qty,
            'remaining_qty' => $this->remaining_qty,
            'status' => $this->status,
            'status_label' => $this->status_label,
            'received_by' => $this->receivedBy ? [
                'id' => $this->receivedBy->id,
                'name' => $this->receivedBy->name,
            ] : null,
            'received_at' => $this->received_at?->format('Y-m-d H:i:s'),
            'receive_notes' => $this->receive_notes,
            'rejection_reason' => $this->rejection_reason,
            'rejected_by' => $this->rejectedBy ? [
                'id' => $this->rejectedBy->id,
                'name' => $this->rejectedBy->name,
            ] : null,
            'rejected_at' => $this->rejected_at?->format('Y-m-d H:i:s'),
            'locations' => LocationResource::collection($this->whenLoaded('locations')),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
