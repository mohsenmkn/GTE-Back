<?php


namespace Modules\PreWarehouse\App\Services;

use Modules\PreWarehouse\App\Models\Warehouse;

class WarehouseService
{
    public function getAll(array $filters = [])
    {
        $query = Warehouse::query()->with('manager');

        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        return $query->latest()->paginate($filters['per_page'] ?? 15);
    }

    public function findById(int $id)
    {
        return Warehouse::with(['manager', 'locations'])->findOrFail($id);
    }

    public function create(array $data)
    {
        return Warehouse::create($data);
    }

    public function update(int $id, array $data)
    {
        $warehouse = Warehouse::findOrFail($id);
        $warehouse->update($data);
        return $warehouse->fresh(['manager', 'locations']);
    }

    public function delete(int $id)
    {
        $warehouse = Warehouse::findOrFail($id);
        return $warehouse->delete();
    }
}
