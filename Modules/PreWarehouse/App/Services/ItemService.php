<?php


namespace Modules\PreWarehouse\App\Services;

use Modules\PreWarehouse\App\Models\Item;

class ItemService
{
    public function getAll(array $filters = [])
    {
        $query = Item::query();

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

    public function create(array $data)
    {
        return Item::create($data);
    }

    public function update(int $id, array $data)
    {
        $item = Item::findOrFail($id);
        $item->update($data);
        return $item->fresh();
    }

    public function delete(int $id)
    {
        $item = Item::findOrFail($id);
        return $item->delete();
    }
}
