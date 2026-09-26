<?php


namespace Modules\WarehouseGtrabar\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'store_ref' => $this->StoreRef,
            'store_code' => $this->StoreCode,
            'store_name' => $this->StoreName,
            'part_ref' => $this->PartRef,
            'part_code' => $this->PartCode,
            'part_name' => $this->PartName,
            'latin_name' => $this->LatinName,
            'technical_specification' => $this->TechnicalSpecification,
            'part_type' => $this->PartType,
            'current_stock' => (float)$this->CurrentStock,
        ];
    }
}
