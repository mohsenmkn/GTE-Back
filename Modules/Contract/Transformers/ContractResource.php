<?php
// Modules/Contract/Transformers/ContractResource.php

namespace Modules\Contract\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;
use Modules\WBS\Transformers\WbsItemResource;
use Modules\Document\Transformers\DocumentResource;

class ContractResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'project_id' => $this->project_id,
            'project' => $this->whenLoaded('project', function() {
                return [
                    'id' => $this->project->id,
                    'name' => $this->project->name,
                    'code' => $this->project->code,
                ];
            }),
            'contractor_id' => $this->contractor_id,
            'contractor' => $this->whenLoaded('contractor', function() {
                return [
                    'id' => $this->contractor->id,
                    'name' => $this->contractor->name,
                ];
            }),
            'contract_number' => $this->contract_number,
            'title' => $this->title,
            'type' => $this->type,
            'type_label' => $this->getTypeLabel(),
            'amount' => (float) $this->amount,
            'paid_amount' => (float) $this->paid_amount,
            'remaining_amount' => (float) $this->remaining_amount,
            'progress_percent' => (float) $this->progress_percent,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'status' => $this->status,
            'status_label' => $this->getStatusLabel(),
            'status_severity' => $this->getStatusSeverity(),
            'description' => $this->description,
            'terms' => $this->terms,
            'is_overdue' => $this->isOverdue(),
            'created_by' => $this->created_by,
            'creator' => $this->whenLoaded('creator', function() {
                return [
                    'id' => $this->creator->id,
                    'name' => $this->creator->name,
                ];
            }),
            'wbs_items' => WbsItemResource::collection($this->whenLoaded('wbsItems')),
            'documents' => DocumentResource::collection($this->whenLoaded('documents')),
            'created_at' => $this->created_at?->toDateString(),
            'updated_at' => $this->updated_at?->toDateString(),
        ];
    }

    private function getTypeLabel(): string
    {
        $labels = [
            'construction' => 'ساخت و ساز',
            'service' => 'خدماتی',
            'consulting' => 'مشاوره',
            'supply' => 'تامین کالا',
            'other' => 'سایر',
        ];
        return $labels[$this->type] ?? $this->type;
    }

    private function getStatusLabel(): string
    {
        $labels = [
            'draft' => 'پیش‌نویس',
            'pending' => 'در انتظار تایید',
            'active' => 'فعال',
            'completed' => 'تکمیل شده',
            'cancelled' => 'لغو شده',
            'suspended' => 'متوقف',
        ];
        return $labels[$this->status] ?? $this->status;
    }

    private function getStatusSeverity(): string
    {
        $severities = [
            'draft' => 'secondary',
            'pending' => 'warning',
            'active' => 'success',
            'completed' => 'info',
            'cancelled' => 'danger',
            'suspended' => 'danger',
        ];
        return $severities[$this->status] ?? 'secondary';
    }
}
