<?php
// Modules/Inventory/Transformers/InventoryCategoryResource.php

namespace Modules\Warehouse\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;

class InventoryCategoryResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'name' => $this->name,
            'code' => $this->code,
            'parent_id' => $this->parent_id,
            'description' => $this->description,
            'status' => $this->status,
            'status_label' => $this->status_label,
            'status_severity' => $this->status_severity,
            'full_path' => $this->full_path,

            // روابط
            'parent' => new InventoryCategoryResource($this->whenLoaded('parent')),
            'children' => InventoryCategoryResource::collection($this->whenLoaded('children')),
            'materials_count' => $this->whenCounted('materials'),
            'children_count' => $this->whenCounted('children'),
            'total_descendants' => $this->when($this->relationLoaded('children'), function() {
                return $this->getDescendantCount();
            }),

            // اطلاعات زمانی
            'created_at' => $this->created_at?->toDateString(),
            'created_at_fa' => $this->created_at?->toDateString(),
            'updated_at' => $this->updated_at?->toDateString(),
        ];
    }

    /**
     * تبدیل تاریخ به شمسی (اگر کتابخانه نصب است)
     */
    private function toJalaliDate($date)
    {
        if (!$date) return null;

        // اگر کتابخانه verta نصب است
        if (class_exists(\Verta\Verta::class)) {
            return \Verta\Verta::instance($date)->formatDate();
        }

        // fallback
        return $date->toDateString();
    }
}
