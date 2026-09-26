<?php
// Modules/Inventory/Transformers/TransactionListResource.php
// برای استفاده در لیست‌های خلاصه تراکنش‌ها

namespace Modules\Warehouse\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;

class TransactionListResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'type_label' => $this->type_label,
            'quantity' => (float) $this->quantity,
            'total_price' => (float) $this->total_price,
            'transaction_date' => $this->transaction_date?->toDateString(),
            'reference_number' => $this->reference_number,
            'material_name' => $this->material?->name,
            'material_code' => $this->material?->code,
            'project_name' => $this->project?->name,
            'wbs_item_name' => $this->wbsItem?->name,
        ];
    }
}
