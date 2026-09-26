<?php
// Modules/WBS/Transformers/WbsItemResource.php

namespace Modules\WBS\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;

class WbsItemResource extends JsonResource
{
    public function toArray($request): array
    {
        // ✅ اگر resource null بود، برگردان
        if (is_null($this->resource)) {
            return [];
        }

        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'parent_id' => $this->parent_id,
            'code' => $this->code,
            'name' => $this->name,
            'category' => $this->category,
            'unit' => $this->unit,
            'quantity' => (float) $this->quantity,
            'unit_price' => (float) $this->unit_price,
            'total_price' => (float) $this->total_price,
            'weight' => (float) $this->weight,
            'progress_percent' => (float) $this->progress_percent,
            'status' => $this->status,
            'description' => $this->description,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'tasks_count' => $this->tasks_count ?? 0,
            'parent' => $this->whenLoaded('parent', function() {
                return new WbsItemResource($this->parent);
            }),
            'children' => $this->whenLoaded('children', function() {
                return WbsItemResource::collection($this->children);
            }),
            'tasks' => $this->whenLoaded('tasks', function() {
                return TaskResource::collection($this->tasks);
            }),
            'effective_progress' => $this->effective_progress, // ✅ درصد واقعی
            'is_auto_progress' => $this->is_auto_progress,     // ✅ آیا خودکار هست؟
        ];
    }
}

