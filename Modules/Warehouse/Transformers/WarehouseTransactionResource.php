<?php

// Modules/Inventory/Transformers/WarehouseTransactionResource.php

namespace Modules\Warehouse\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Project\Transformers\ProjectResource;
use Modules\WBS\Transformers\WbsItemResource;
use Modules\Contract\Transformers\ContractResource;

class WarehouseTransactionResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'material_id' => $this->material_id,
            'project_id' => $this->project_id,
            'wbs_item_id' => $this->wbs_item_id,
            'contract_id' => $this->contract_id,

            // اطلاعات تراکنش
            'type' => $this->type,
            'type_label' => $this->type_label,
            'type_icon' => $this->getTypeIcon(),
            'type_severity' => $this->getTypeSeverity(),
            'quantity' => (float)$this->quantity,
            'unit_price' => (float)$this->unit_price,
            'total_price' => (float)$this->total_price,
            'transaction_date' => $this->transaction_date?->toDateString(),
            'reference_number' => $this->reference_number,
            'description' => $this->description,

            // روابط
            'material' => new MaterialResource($this->whenLoaded('material')),
            'project' => new ProjectResource($this->whenLoaded('project')),
            'wbs_item' => new WbsItemResource($this->whenLoaded('wbsItem')),
            'contract' => new ContractResource($this->whenLoaded('contract')),
            'creator' => $this->whenLoaded('creator', function () {
                return [
                    'id' => $this->creator->id,
                    'name' => $this->creator->name,
                ];
            }),

            // اطلاعات زمانی
            'created_at' => $this->created_at?->toDateString(),
            'created_at_fa' => $this->created_at?->toDateString(),
            'created_at_time' => $this->created_at?->toTimeString(),
            'updated_at' => $this->updated_at?->toDateString(),
        ];
    }

    /**
     * دریافت آیکون نوع تراکنش
     */
    private function getTypeIcon()
    {
        $icons = [
            'purchase' => 'pi pi-shopping-cart',
            'sale' => 'pi pi-dollar',
            'transfer' => 'pi pi-arrow-right-arrow-left',
            'adjustment' => 'pi pi-pencil',
            'return' => 'pi pi-undo',
            'damage' => 'pi pi-exclamation-triangle',
        ];
        return $icons[$this->type] ?? 'pi pi-circle';
    }

    /**
     * دریافت Severity نوع تراکنش
     */
    private function getTypeSeverity()
    {
        $severities = [
            'purchase' => 'success',
            'sale' => 'info',
            'transfer' => 'warning',
            'adjustment' => 'secondary',
            'return' => 'info',
            'damage' => 'danger',
        ];
        return $severities[$this->type] ?? 'secondary';
    }

    /**
     * تبدیل تاریخ به شمسی (اگر کتابخانه نصب است)
     */
    private function toJalaliDate($date)
    {
        if (!$date) return null;

        if (class_exists(\Verta\Verta::class)) {
            return \Verta\Verta::instance($date)->formatDate();
        }

        return $date->toDateString();
    }
}
