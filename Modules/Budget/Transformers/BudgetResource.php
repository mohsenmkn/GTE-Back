<?php

namespace Modules\Budget\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;

class BudgetResource extends JsonResource
{
    public function toArray(Request|\Illuminate\Http\Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'title' => $this->title,
            'type' => $this->type,
            'amount' => $this->amount,
            'used_amount' => $this->used_amount,
            'remaining' => $this->remaining,
            'usage_percent' => $this->usage_percent,
            'fiscal_year' => $this->fiscal_year,
            'description' => $this->description,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
