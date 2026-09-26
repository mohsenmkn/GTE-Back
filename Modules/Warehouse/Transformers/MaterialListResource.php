<?php
// Modules/Inventory/Transformers/MaterialListResource.php
// برای استفاده در لیست‌های خلاصه (بدون بارگذاری روابط سنگین)

namespace Modules\Warehouse\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;

class MaterialListResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'unit' => $this->unit,
            'unit_price' => (float) $this->unit_price,
            'current_stock' => (float) $this->current_stock,
            'min_stock' => (float) $this->min_stock,
            'max_stock' => (float) $this->max_stock,
            'status' => $this->status,
            'status_label' => $this->status_label,
            'is_low_stock' => $this->current_stock <= $this->min_stock,
            'category_name' => $this->category?->name,
        ];
    }
}
