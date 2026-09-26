<?php


namespace Modules\WarehouseGtrabar\App\Repositories;

use Modules\WarehouseGtrabar\App\Models\SqlServer\Part;
use Modules\WarehouseGtrabar\App\Models\SqlServer\Store;
use Modules\WarehouseGtrabar\App\Models\SqlServer\InventoryVoucher;
use Modules\WarehouseGtrabar\App\Models\SqlServer\InventoryVoucherItem;
use Modules\WarehouseGtrabar\App\Models\SqlServer\InventoryVoucherSpecification;
use Illuminate\Support\Facades\DB;

class StockRepository
{
    /**
     * دریافت موجودی انبار برای یک Store خاص
     */
    public function getStockByStore(int $storeId, ?string $search = null, int $perPage = 20)
    {
        $query = DB::connection('gtarabar')
            ->table('LGS3.InventoryVoucherItem as ivi')
            ->select([
                'iv.StoreRef',
                's.Code as StoreCode',
                's.Name as StoreName',
                'ivi.PartRef',
                'p.Code as PartCode',
                'p.Name as PartName',
                'p.LatinName',
                'p.TechnicalSpecification',
                'p.PartType',
                DB::raw('SUM(
                    CASE
                        WHEN ivs.Direction = 1 THEN ivi.MajorUnitQuantity
                        WHEN ivs.Direction = 2 THEN -ivi.MajorUnitQuantity
                        ELSE 0
                    END
                ) as CurrentStock')
            ])
            ->join('LGS3.InventoryVoucher as iv', 'iv.InventoryVoucherID', '=', 'ivi.InventoryVoucherRef')
            ->join('LGS3.InventoryVoucherSpecification as ivs', 'ivs.InventoryVoucherSpecificationID', '=', 'iv.InventoryVoucherSpecificationRef')
            ->join('LGS3.Store as s', 's.StoreID', '=', 'iv.StoreRef')
            ->join('LGS3.Part as p', 'p.PartID', '=', 'ivi.PartRef')
            ->where('iv.StoreRef', $storeId)
            ->groupBy(
                'iv.StoreRef', 's.Code', 's.Name',
                'ivi.PartRef', 'p.Code', 'p.Name',
                'p.LatinName', 'p.TechnicalSpecification', 'p.PartType'
            )
            ->havingRaw('SUM(
                CASE
                    WHEN ivs.Direction = 1 THEN ivi.MajorUnitQuantity
                    WHEN ivs.Direction = 2 THEN -ivi.MajorUnitQuantity
                    ELSE 0
                END
            ) <> 0');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('p.Code', 'like', "%{$search}%")
                    ->orWhere('p.Name', 'like', "%{$search}%")
                    ->orWhere('p.LatinName', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('p.Code')->paginate($perPage);
    }

    /**
     * دریافت لیست انبارها
     */
    public function getStores()
    {
        return Store::where('State', 1)
            ->orderBy('Code')
            ->get(['StoreID', 'Code', 'Name']);
    }

    /**
     * جستجوی قطعات
     */
    public function searchParts(string $search, int $limit = 20)
    {
        return Part::where(function ($query) use ($search) {
            $query->where('Code', 'like', "%{$search}%")
                ->orWhere('Name', 'like', "%{$search}%")
                ->orWhere('LatinName', 'like', "%{$search}%");
        })
            ->where('State', 1)
            ->limit($limit)
            ->get(['PartID', 'Code', 'Name', 'LatinName', 'TechnicalSpecification']);
    }
}
