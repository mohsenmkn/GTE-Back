<?php

namespace Modules\PreWarehouse\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'item' => $this->item ? [
                'id' => $this->item->id,
                'name' => $this->item->name,
                'code' => $this->item->code,
            ] : null,
            'quantity' => $this->quantity,
            'unit_of_measurement' => $this->unit_of_measurement,
            'description' => $this->description,
            'status' => $this->status,
            'status_label' => $this->status_label,
            'total_allocated_qty' => $this->total_allocated_qty,
            'total_received_qty' => $this->total_received_qty,
            'remaining_qty' => $this->remaining_qty,
            'allocation_progress' => $this->allocation_progress,
            'commercial_user' => $this->commercialUser ? [
                'id' => $this->commercialUser->id,
                'name' => $this->commercialUser->name,
                'mobile' => $this->commercialUser->mobile,
                'position' => $this->commercialUser->employeePosition?->post_title,
                'unit' => $this->commercialUser->employeePosition?->unit?->title,
            ] : null,
            'target_unit' => $this->targetUnit ? [
                'id' => $this->targetUnit->id,
                'title' => $this->targetUnit->title,
            ] : null,
            'allocations' => AllocationResource::collection($this->whenLoaded('allocations')),
            'approvals' => ApprovalResource::collection($this->whenLoaded('approvals')),
            'metadata' => $this->metadata,
            'allocated_at' => $this->allocated_at?->format('Y-m-d H:i:s'),
            'fully_received_at' => $this->fully_received_at?->format('Y-m-d H:i:s'),
            'custodian_approved_at' => $this->custodian_approved_at?->format('Y-m-d H:i:s'),
            'location_assigned_at' => $this->location_assigned_at?->format('Y-m-d H:i:s'),
            //'finalized_at' => $this->finalized_at?->format('Y-m-d H:i:s'),
            'rejected_at' => $this->rejected_at?->format('Y-m-d H:i:s'),
            'rejection_reason' => $this->rejection_reason,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            'voucher_number' => $this->voucher_number,
            'finalized_by' => $this->finalizedBy ? [
                'id' => $this->finalizedBy->id,
                'name' => $this->finalizedBy->name,
            ] : null,
            'finalized_at' => $this->finalized_at?->format('Y-m-d H:i:s'),
            'is_owner' => $this->commercial_user_id === auth()->id(),  // ✅ برای نمایش دکمه
        ];
    }
}
