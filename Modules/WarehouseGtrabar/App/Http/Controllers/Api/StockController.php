<?php


namespace Modules\WarehouseGtrabar\App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Modules\WarehouseGtrabar\App\Http\Resources\StockResource;
use Modules\WarehouseGtrabar\App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockController extends Controller
{
    public function __construct(
        private StockService $stockService
    )
    {
    }

    /**
     * GET /api/warehouse-gtrabar/stock
     * لیست موجودی انبار
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'store_id' => ['required', 'integer'],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $stock = $this->stockService->getStockByStore(
            $request->store_id,
            $request->search,
            $request->per_page ?? 20
        );

        return response()->json([
            'success' => true,
            'data' => StockResource::collection($stock),
            'pagination' => [
                'current_page' => $stock->currentPage(),
                'last_page' => $stock->lastPage(),
                'per_page' => $stock->perPage(),
                'total' => $stock->total(),
            ]
        ]);
    }

    /**
     * GET /api/warehouse-gtrabar/stores
     * لیست انبارها
     */
    public function stores(): JsonResponse
    {
        $stores = $this->stockService->getStores();

        return response()->json([
            'success' => true,
            'data' => $stores,
        ]);
    }

    /**
     * GET /api/warehouse-gtrabar/parts/search
     * جستجوی قطعات
     */
    public function searchParts(Request $request): JsonResponse
    {
        $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:100'],
        ]);

        $parts = $this->stockService->searchParts($request->q);

        return response()->json([
            'success' => true,
            'data' => $parts,
        ]);
    }
}
