<?php

namespace Modules\TripExpense\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpenseLimitResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'category' => new ExpenseCategoryResource($this->whenLoaded('category')),
            'limit_type' => $this->limit_type,
            'limit_type_label' => $this->limit_type === 'per_trip' ? 'سقف هر سفر' : 'سقف ماهانه',
            'amount' => (float) $this->amount,
            'amount_formatted' => number_format($this->amount) . ' تومان',
            'effective_from' => $this->effective_from?->format('Y-m-d'),
            'effective_to' => $this->effective_to?->format('Y-m-d'),
            'is_active' => $this->is_active,
            'creator' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ]),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
