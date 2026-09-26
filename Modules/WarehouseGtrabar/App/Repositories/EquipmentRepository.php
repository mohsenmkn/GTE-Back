<?php


namespace Modules\WarehouseGtrabar\App\Repositories;

use Modules\WarehouseGtrabar\App\Models\Equipment;

class EquipmentRepository
{
    public function getAll(?string $search = null, ?int $type = null, int $perPage = 20)
    {
        $query = Equipment::with(['parent', 'currentInstallations']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhere('title_en', 'like', "%{$search}%");
            });
        }

        if ($type !== null) {
            $query->where('type', $type);
        }

        return $query->orderBy('code')->paginate($perPage);
    }

    public function findById(int $id)
    {
        return Equipment::with(['parent', 'children', 'installations.installer', 'installations.remover'])
            ->findOrFail($id);
    }

    public function create(array $data): Equipment
    {
        return Equipment::create($data);
    }

    public function update(Equipment $equipment, array $data): Equipment
    {
        $equipment->update($data);
        return $equipment->fresh();
    }

    public function delete(Equipment $equipment): bool
    {
        return $equipment->delete();
    }

    public function getTypes(): array
    {
        return [
            1 => 'کامیون',
            2 => 'لودر',
            3 => 'بیل مکانیکی',
            4 => 'جرثقیل',
            5 => 'کمپرسور',
            6 => 'ژنراتور',
            7 => 'سایر',
        ];
    }
}
