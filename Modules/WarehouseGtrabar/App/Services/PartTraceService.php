<?php


namespace Modules\WarehouseGtrabar\App\Services;

use Modules\WarehouseGtrabar\App\Repositories\PartTraceRepository;
use Modules\WarehouseGtrabar\App\Models\PartInstallation;

class PartTraceService
{
    public function __construct(
        private PartTraceRepository $traceRepository
    )
    {
    }

    public function getAll(?int $equipmentId = null, ?string $partCode = null, bool $activeOnly = false, int $perPage = 20)
    {
        return $this->traceRepository->getAll($equipmentId, $partCode, $activeOnly, $perPage);
    }

    public function findById(int $id)
    {
        return $this->traceRepository->findById($id);
    }

    public function create(array $data): PartInstallation
    {
        return $this->traceRepository->create($data);
    }

    public function removePart(int $id, ?int $userId = null): PartInstallation
    {
        return $this->traceRepository->removePart($id, $userId);
    }

    public function getEquipmentTrace(int $equipmentId)
    {
        return $this->traceRepository->getEquipmentTrace($equipmentId);
    }

    public function getPartHistory(string $partCode)
    {
        return $this->traceRepository->getPartHistory($partCode);
    }
}
