<?php


namespace Modules\WarehouseGtrabar\App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Modules\WarehouseGtrabar\App\Models\Equipment;
use Modules\WarehouseGtrabar\App\Http\Requests\StoreEquipmentRequest;
use Modules\WarehouseGtrabar\App\Http\Resources\EquipmentResource;
use Modules\WarehouseGtrabar\App\Services\EquipmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EquipmentController extends Controller
{
    public function __construct(
        private EquipmentService $equipmentService
    )
    {
    }

    /**
     * GET /api/warehouse-gtrabar/equipment
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'integer', 'in:1,2,3,4,5,6,7'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $equipment = $this->equipmentService->getAll(
            $request->search,
            $request->type,
            $request->per_page ?? 20
        );

        return response()->json([
            'success' => true,
            'data' => EquipmentResource::collection($equipment),
            'pagination' => [
                'current_page' => $equipment->currentPage(),
                'last_page' => $equipment->lastPage(),
                'per_page' => $equipment->perPage(),
                'total' => $equipment->total(),
            ],
            'types' => $this->equipmentService->getTypes(),
        ]);
    }

    /**
     * GET /api/warehouse-gtrabar/equipment/{id}
     */
    public function show(int $id): JsonResponse
    {
        $equipment = $this->equipmentService->findById($id);

        return response()->json([
            'success' => true,
            'data' => new EquipmentResource($equipment),
        ]);
    }

    /**
     * POST /api/warehouse-gtrabar/equipment
     */
    public function store(StoreEquipmentRequest $request): JsonResponse
    {
        $equipment = $this->equipmentService->create([
            ...$request->validated(),
            'created_by' => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تجهیز با موفقیت ایجاد شد',
            'data' => new EquipmentResource($equipment),
        ], 201);
    }

    /**
     * PUT /api/warehouse-gtrabar/equipment/{id}
     */
    public function update(StoreEquipmentRequest $request, int $id): JsonResponse
    {
        $equipment = $this->equipmentService->findById($id);

        $equipment = $this->equipmentService->update($equipment, [
            ...$request->validated(),
            'updated_by' => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تجهیز با موفقیت به‌روزرسانی شد',
            'data' => new EquipmentResource($equipment),
        ]);
    }

    /**
     * DELETE /api/warehouse-gtrabar/equipment/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $equipment = $this->equipmentService->findById($id);
        $this->equipmentService->delete($equipment);

        return response()->json([
            'success' => true,
            'message' => 'تجهیز با موفقیت حذف شد',
        ]);
    }
}
