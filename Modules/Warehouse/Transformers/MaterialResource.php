<?php
// Modules/Inventory/Transformers/MaterialResource.php

namespace Modules\Warehouse\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;

class MaterialResource extends JsonResource
{
    public function toArray($request)
    {
        $stockRatio = $this->max_stock > 0
            ? round(($this->current_stock / $this->max_stock) * 100, 2)
            : 0;

        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'category_id' => $this->category_id,
            'code' => $this->code,
            'name' => $this->name,
            'unit' => $this->unit,
            'unit_price' => (float) $this->unit_price,
            'current_stock' => (float) $this->current_stock,
            'min_stock' => (float) $this->min_stock,
            'max_stock' => (float) $this->max_stock,
            'description' => $this->description,
            'status' => $this->status,
            'status_label' => $this->status_label,
            'status_severity' => $this->status_severity,

            // محاسبات
            'stock_ratio' => $stockRatio,
            'stock_status' => $this->getStockStatus(),
            'stock_status_label' => $this->getStockStatusLabel(),
            'stock_status_severity' => $this->getStockStatusSeverity(),
            'is_low_stock' => $this->current_stock <= $this->min_stock,
            'shortage_amount' => max(0, $this->min_stock - $this->current_stock),
            'total_value' => (float) ($this->current_stock * $this->unit_price),

            // روابط
            'category' => new InventoryCategoryResource($this->whenLoaded('category')),
            'transactions_count' => $this->whenCounted('transactions'),
            'transactions' => WarehouseTransactionResource::collection($this->whenLoaded('transactions')),

            // اطلاعات زمانی
            'created_at' => $this->created_at?->toDateString(),
            'updated_at' => $this->updated_at?->toDateString(),
        ];
    }

    /**
     * دریافت وضعیت موجودی
     */
    private function getStockStatus()
    {
        if ($this->max_stock == 0) return 'unknown';

        $ratio = ($this->current_stock / $this->max_stock) * 100;

        if ($ratio <= 20) return 'critical';
        if ($ratio <= 50) return 'low';
        if ($ratio <= 80) return 'medium';
        return 'good';
    }

    /**
     * دریافت برچسب وضعیت موجودی
     */
    private function getStockStatusLabel()
    {
        $labels = [
            'critical' => 'بحرانی',
            'low' => 'کم',
            'medium' => 'متوسط',
            'good' => 'خوب',
            'unknown' => 'نامشخص',
        ];
        return $labels[$this->getStockStatus()] ?? 'نامشخص';
    }

    /**
     * دریافت Severity وضعیت موجودی
     */
    private function getStockStatusSeverity()
    {
        $severities = [
            'critical' => 'danger',
            'low' => 'warning',
            'medium' => 'info',
            'good' => 'success',
            'unknown' => 'secondary',
        ];
        return $severities[$this->getStockStatus()] ?? 'secondary';
    }
}
