<?php

namespace Modules\PettyCash\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;

class PettyCashResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'              => $this->id,
            'project_id'      => $this->project_id,
            'name'            => $this->name,
            'initial_amount'  => (float) $this->initial_amount,
            'current_balance' => (float) $this->current_balance,
            'is_active'       => (bool) $this->is_active,
            'status'          => $this->is_active ? 'active' : 'inactive',
            'transactions_count' => $this->whenCounted('transactions'),
            'transactions'    => PettyCashTransactionResource::collection($this->whenLoaded('transactions')),
            'created_at'      => $this->created_at?->toDateString(),
            'updated_at'      => $this->updated_at?->toDateString(),
        ];
    }
}
