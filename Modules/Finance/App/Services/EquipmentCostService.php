<?php

namespace Modules\Finance\App\Services;

use Modules\Finance\App\Repositories\EquipmentCostRepository;

class EquipmentCostService
{
    protected $repository;

    public function __construct(EquipmentCostRepository $repository)
    {
        $this->repository = $repository;
    }

    public function getAccessibleEquipmentTypes(): array
    {
        return $this->repository->getAccessibleEquipmentTypes();
    }

    public function getAllEquipmentTypes(): array
    {
        return $this->repository->getAllEquipmentTypesFromDB();
    }

    public function canAccessEquipmentType(int $dlTypeRef): bool
    {
        return $this->repository->canAccessEquipmentType($dlTypeRef);
    }

    public function getEquipmentCostReport(
        string $equipmentCode,
        int $typeId,
        string $fromDate,
        string $toDate
    ): array {
        $costs = $this->repository->getEquipmentCosts(
            $equipmentCode, $typeId, $fromDate, $toDate
        );

        $totalCost = $this->repository->getTotalCost(
            $equipmentCode, $typeId, $fromDate, $toDate
        );

        return [
            'costs' => $costs,
            'total' => (float) $totalCost,
            'count' => count($costs),
            'equipment_code' => $equipmentCode,
            'type_id' => $typeId,
            'from_date' => $fromDate,
            'to_date' => $toDate,
        ];
    }

    public function getEquipmentsByType(int $typeId): array
    {
        return $this->repository->getEquipmentsByType($typeId);
    }
}
