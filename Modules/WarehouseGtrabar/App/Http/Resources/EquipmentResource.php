<?php


namespace Modules\WarehouseGtrabar\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EquipmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'title' => $this->title,
            'title_en' => $this->title_en,
            'type' => $this->type,
            'type_label' => $this->getTypeLabel(),
            'description' => $this->description,
            'state' => $this->state,
            'state_label' => $this->getStateLabel(),
            'parent_equipment_id' => $this->parent_equipment_id,
            'parent' => $this->whenLoaded('parent', fn() => new self($this->parent)),
            'children_count' => $this->whenLoaded('children', fn() => $this->children->count()),
            'installations_count' => $this->whenLoaded('installations', fn() => $this->installations->count()),
            'current_installations' => PartInstallationResource::collection($this->whenLoaded('currentInstallations')),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }

    private function getTypeLabel(): string
    {
        $types = [
            1 => 'کامیون',
            2 => 'لودر',
            3 => 'بیل مکانیکی',
            4 => 'جرثقیل',
            5 => 'کمپرسور',
            6 => 'ژنراتور',
            7 => 'سایر',
        ];
        return $types[$this->type] ?? 'نامشخص';
    }

    private function getStateLabel(): string
    {
        $states = [
            1 => 'فعال',
            2 => 'غیرفعال',
            3 => 'در تعمیر',
        ];
        return $states[$this->state] ?? 'نامشخص';
    }
}
