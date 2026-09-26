<?php


namespace Modules\WarehouseGtrabar\App\Services;

use Modules\WarehouseGtrabar\App\Repositories\EquipmentRepository;
use Modules\WarehouseGtrabar\App\Models\Equipment;

class EquipmentService
{
    public function __construct(
        private EquipmentRepository $equipmentRepository
    )
    {
    }

    public function getAll(?string $search = null, ?int $type = null, int $perPage = 20)
    {
        return $this->equipmentRepository->getAll($search, $type, $perPage);
    }

    public function findById(int $id)
    {
        return $this->equipmentRepository->findById($id);
    }

    public function create(array $data): Equipment
    {
        return $this->equipmentRepository->create($data);
    }

    public function update(Equipment $equipment, array $data): Equipment
    {
        return $this->equipmentRepository->update($equipment, $data);
    }

    public function delete(Equipment $equipment): bool
    {
        return $this->equipmentRepository->delete($equipment);
    }

    public function getTypes(): array
    {
        return $this->equipmentRepository->getTypes();
    }
}
