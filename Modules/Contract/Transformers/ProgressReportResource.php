<?php
// Modules/Contract/Transformers/ProgressReportResource.php

namespace Modules\Contract\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;

class ProgressReportResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'            => $this->id,
            'contract_id'   => $this->contract_id,
            'report_number' => $this->report_number,
            'report_date'   => $this->report_date?->toDateString(),
            'from_date'     => $this->from_date?->toDateString(),
            'to_date'       => $this->to_date?->toDateString(),
            'description'   => $this->description,
            'total_amount'  => $this->total_amount,
            'paid_amount'   => $this->paid_amount,
            'status'        => $this->status,
            'approved_at'   => $this->approved_at?->toDateTimeString(),
            'approver'      => $this->whenLoaded('approver', fn() => [
                'id'   => $this->approver->id,
                'name' => $this->approver->name,
            ]),
            'created_at'    => $this->created_at->toDateTimeString(),
        ];
    }
}
