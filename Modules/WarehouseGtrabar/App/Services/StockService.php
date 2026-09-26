<?php


namespace Modules\WarehouseGtrabar\App\Services;

use Modules\WarehouseGtrabar\App\Repositories\StockRepository;

class StockService
{
    public function __construct(
        private StockRepository $stockRepository
    )
    {
    }

    public function getStockByStore(int $storeId, ?string $search = null, int $perPage = 20)
    {
        return $this->stockRepository->getStockByStore($storeId, $search, $perPage);
    }

    public function getStores()
    {
        return $this->stockRepository->getStores();
    }

    public function searchParts(string $search, int $limit = 20)
    {
        return $this->stockRepository->searchParts($search, $limit);
    }
}
