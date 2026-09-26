<?php

namespace Modules\PreWarehouse\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApprovalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'approver_type' => $this->approver_type,
            'approver_type_label' => $this->approver_type === 'custodian' ? 'متولی کالا' : 'مسئول انبار',
            'approver' => $this->approver ? [
                'id' => $this->approver->id,
                'name' => $this->approver->name,
                'position' => $this->approver->employeePosition?->post_title,
            ] : null,
            'status' => $this->status,
            'status_label' => $this->status_label,
            'approval_notes' => $this->approval_notes,
            'rejection_reason' => $this->rejection_reason,
            'approved_at' => $this->approved_at?->format('Y-m-d H:i:s'),
            'rejected_at' => $this->rejected_at?->format('Y-m-d H:i:s'),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
