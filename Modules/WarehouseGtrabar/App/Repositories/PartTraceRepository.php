<?php


namespace Modules\WarehouseGtrabar\App\Repositories;

use Modules\WarehouseGtrabar\App\Models\PartInstallation;
use Modules\WarehouseGtrabar\App\Models\Equipment;

class PartTraceRepository
{
    public function getAll(?int $equipmentId = null, ?string $partCode = null, bool $activeOnly = false, int $perPage = 20)
    {
        $query = PartInstallation::with(['equipment', 'installer', 'remover']);

        if ($equipmentId) {
            $query->where('equipment_id', $equipmentId);
        }

        if ($partCode) {
            $query->where('part_code', 'like', "%{$partCode}%");
        }

        if ($activeOnly) {
            $query->active();
        }

        return $query->orderBy('installed_at', 'desc')->paginate($perPage);
    }

    public function findById(int $id)
    {
        return PartInstallation::with(['equipment', 'installer', 'remover'])
            ->findOrFail($id);
    }

    public function create(array $data): PartInstallation
    {
        return PartInstallation::create($data);
    }

    public function removePart(int $id, ?int $userId = null): PartInstallation
    {
        $installation = PartInstallation::findOrFail($id);

        if ($installation->removed_at) {
            throw new \Exception('این قطعه قبلاً خارج شده است');
        }

        $installation->update([
            'removed_at' => now(),
            'removed_by' => $userId,
        ]);

        return $installation->fresh();
    }

    public function getEquipmentTrace(int $equipmentId)
    {
        return PartInstallation::where('equipment_id', $equipmentId)
            ->with(['installer', 'remover'])
            ->orderBy('installed_at', 'desc')
            ->get();
    }

    public function getPartHistory(string $partCode)
    {
        return PartInstallation::where('part_code', $partCode)
            ->with(['equipment', 'installer', 'remover'])
            ->orderBy('installed_at', 'desc')
            ->get();
    }
}
