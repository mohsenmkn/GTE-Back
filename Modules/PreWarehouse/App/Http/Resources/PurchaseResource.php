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
            'supplier' => $this->supplier,
            'brand' => $this->brand,
            'item_code' => $this->item_code,
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
            'allocations' => $this->allocations->map(fn ($a) => [
                'id' => $a->id,
                'warehouse' => [
                    'id' => $a->warehouse->id,
                    'name' => $a->warehouse->name,
                    'code' => $a->warehouse->code,
                    'quarantine_location' => $a->warehouse->quarantineLocation ? [
                        'id' => $a->warehouse->quarantineLocation->id,
                        'name' => $a->warehouse->quarantineLocation->name,
                    ] : null,
                ],
                'allocated_qty' => $a->allocated_qty,
                'received_qty' => $a->received_qty,
                'temporary_exit_qty' => $a->temporary_exit_qty ?? 0,
                'status' => $a->status,
                'status_label' => $a->status_label,
                'status_color' => $a->status_color,
                'remaining_in_quarantine' => $a->allocated_qty - ($a->temporary_exit_qty ?? 0) - $a->received_qty,
                'locations' => $a->locations->map(fn ($location) => [
                    'id' => $location->id,
                    'warehouse_id' => $location->warehouse_id,
                    'warehouse_location_id' => $location->warehouse_location_id,
                    'location_name' => $location->location_name,
                    'section_code' => $location->section_code,
                    'assigned_qty' => $location->assigned_qty,
                    'description' => $location->description,
                    'warehouse_location' => $location->warehouseLocation ? [
                        'id' => $location->warehouseLocation->id,
                        'name' => $location->warehouseLocation->name,
                        'code' => $location->warehouseLocation->code,
                        'section' => $location->warehouseLocation->section,
                    ] : null,
                ]),
                'temporary_exits' => $a->temporaryExits->map(fn ($exit) => [
                    'id' => $exit->id,
                    'quantity' => $exit->quantity,
                    'target_code' => $exit->target_code,
                    'target_description' => $exit->target_description,
                ]),
            ]),
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

            'warehouse_receipt_number' => $this->warehouse_receipt_number,
            'voucher_entered_by' => $this->voucherEnteredBy ? [
                'id' => $this->voucherEnteredBy->id,
                'name' => $this->voucherEnteredBy->name,
            ] : null,
            'receipt_entered_by' => $this->receiptEnteredBy ? [
                'id' => $this->receiptEnteredBy->id,
                'name' => $this->receiptEnteredBy->name,
            ] : null,
            'voucher_entered_at' => $this->voucher_entered_at?->format('Y-m-d H:i:s'),
            'receipt_entered_at' => $this->receipt_entered_at?->format('Y-m-d H:i:s'),
            // برگشت کالا
            'warehouse_return_scheduled_at' => $this->warehouse_return_scheduled_at?->format('Y-m-d H:i:s'),
            'warehouse_return_scheduled_by' => $this->warehouseReturnScheduledBy ? [
                'id' => $this->warehouseReturnScheduledBy->id,
                'name' => $this->warehouseReturnScheduledBy->name,
            ] : null,

            'commercial_received_at' => $this->commercial_received_at?->format('Y-m-d H:i:s'),
            'commercial_received_by' => $this->commercialReceivedBy ? [
                'id' => $this->commercialReceivedBy->id,
                'name' => $this->commercialReceivedBy->name,
            ] : null,

            'supplier_returned_at' => $this->supplier_returned_at?->format('Y-m-d H:i:s'),
            'supplier_returned_by' => $this->supplierReturnedBy ? [
                'id' => $this->supplierReturnedBy->id,
                'name' => $this->supplierReturnedBy->name,
            ] : null,


            'temporary_exits' => $this->temporaryExits->map(fn ($exit) => [
                'id' => $exit->id,
                'allocation_id' => $exit->allocation_id,
                'quantity' => $exit->quantity,
                'target_type' => $exit->target_type,
                'target_type_label' => $exit->target_type_label,
                'target_code' => $exit->target_code,
                'target_description' => $exit->target_description,
                'site_name' => $exit->site_name,
                'registered_by' => [
                    'id' => $exit->registeredBy->id,
                    'name' => $exit->registeredBy->name,
                ],
                'exit_date' => $exit->exit_date?->format('Y-m-d H:i:s'),
                'notes' => $exit->notes,
            ]),
            'total_temporary_exit_qty' => $this->temporaryExits->sum('quantity'),
        ];
    }
}
