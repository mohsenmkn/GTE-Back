<?php


namespace Modules\WarehouseGtrabar\App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Modules\WarehouseGtrabar\App\Http\Requests\StorePartInstallationRequest;
use Modules\WarehouseGtrabar\App\Http\Resources\PartInstallationResource;
use Modules\WarehouseGtrabar\App\Services\PartTraceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PartTraceController extends Controller
{
    public function __construct(
        private PartTraceService $traceService
    )
    {
    }

    /**
     * GET /api/warehouse-gtrabar/part-trace
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'equipment_id' => ['nullable', 'integer', 'exists:equipment,id'],
            'part_code' => ['nullable', 'string', 'max:64'],
            'active_only' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $traces = $this->traceService->getAll(
            $request->equipment_id,
            $request->part_code,
            $request->boolean('active_only', false),
            $request->per_page ?? 20
        );

        return response()->json([
            'success' => true,
            'data' => PartInstallationResource::collection($traces),
            'pagination' => [
                'current_page' => $traces->currentPage(),
                'last_page' => $traces->lastPage(),
                'per_page' => $traces->perPage(),
                'total' => $traces->total(),
            ]
        ]);
    }

    /**
     * GET /api/warehouse-gtrabar/part-trace/{id}
     */
    public function show(int $id): JsonResponse
    {
        $trace = $this->traceService->findById($id);

        return response()->json([
            'success' => true,
            'data' => new PartInstallationResource($trace),
        ]);
    }

    /**
     * POST /api/warehouse-gtrabar/part-trace
     */
    public function store(StorePartInstallationRequest $request): JsonResponse
    {
        $installation = $this->traceService->create([
            ...$request->validated(),
            'installed_by' => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'نصب قطعه با موفقیت ثبت شد',
            'data' => new PartInstallationResource($installation),
        ], 201);
    }

    /**
     * POST /api/warehouse-gtrabar/part-trace/{id}/remove
     */
    public function remove(int $id): JsonResponse
    {
        try {
            $installation = $this->traceService->removePart($id, auth()->id());

            return response()->json([
                'success' => true,
                'message' => 'خروج قطعه با موفقیت ثبت شد',
                'data' => new PartInstallationResource($installation),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * GET /api/warehouse-gtrabar/equipment/{equipmentId}/trace
     */
    public function equipmentTrace(int $equipmentId): JsonResponse
    {
        $traces = $this->traceService->getEquipmentTrace($equipmentId);

        return response()->json([
            'success' => true,
            'data' => PartInstallationResource::collection($traces),
        ]);
    }

    /**
     * GET /api/warehouse-gtrabar/part/{partCode}/history
     */
    public function partHistory(string $partCode): JsonResponse
    {
        $history = $this->traceService->getPartHistory($partCode);

        return response()->json([
            'success' => true,
            'data' => PartInstallationResource::collection($history),
        ]);
    }
}
