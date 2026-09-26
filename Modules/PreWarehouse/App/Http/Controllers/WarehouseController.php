<?php

namespace Modules\PreWarehouse\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\PreWarehouse\App\Http\Resources\WarehouseResource;
use Modules\PreWarehouse\App\Models\Warehouse;
use Modules\PreWarehouse\App\Services\WarehouseService;
use Modules\PreWarehouse\App\Models\WarehouseLocation;

class WarehouseController extends Controller
{
    protected $service;

    public function __construct(WarehouseService $service)
    {
        $this->service = $service;
    }

    /**
     * لیست انبارها
     */
    public function index(Request $request)
    {
        $filters = $request->only(['search', 'per_page', 'is_active']);
        $warehouses = $this->service->getAll($filters);

        return response()->json([
            'data' => WarehouseResource::collection($warehouses),
            'meta' => [
                'total' => $warehouses->total(),
                'per_page' => $warehouses->perPage(),
                'current_page' => $warehouses->currentPage(),
                'last_page' => $warehouses->lastPage(),
            ],
        ]);
    }

    /**
     * لیست همه انبارها (برای Select)
     */
    public function all(Request $request)
    {
        $query = \Modules\PreWarehouse\App\Models\Warehouse::query()->active()->orderBy('name');

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->input('search') . '%');
        }

        $warehouses = $query->limit(100)->get();

        return response()->json([
            'data' => $warehouses->map(fn ($w) => [
                'id' => $w->id,
                'name' => $w->name,
                'code' => $w->code,
            ]),
        ]);
    }

    /**
     * نمایش جزئیات
     */
    public function show(int $id)
    {
        $warehouse = $this->service->findById($id);

        return response()->json([
            'data' => new WarehouseResource($warehouse),
        ]);
    }

    /**
     * ثبت انبار جدید
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:warehouses,code',
            'description' => 'nullable|string|max:1000',
            'address' => 'nullable|string|max:500',
            'phone' => 'nullable|string|max:20',
            'manager_id' => 'nullable|exists:users,id',
            'is_active' => 'boolean',
        ], [
            'name.required' => 'نام انبار الزامی است',
            'code.required' => 'کد انبار الزامی است',
            'code.unique' => 'کد انبار تکراری است',
        ]);

        $warehouse = $this->service->create($validated);

        return response()->json([
            'message' => 'انبار با موفقیت ثبت شد',
            'data' => new WarehouseResource($warehouse),
        ], 201);
    }

    /**
     * ویرایش انبار
     */
    public function update(Request $request, int $id)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:warehouses,code,' . $id,
            'description' => 'nullable|string|max:1000',
            'address' => 'nullable|string|max:500',
            'phone' => 'nullable|string|max:20',
            'manager_id' => 'nullable|exists:users,id',
            'is_active' => 'boolean',
        ], [
            'name.required' => 'نام انبار الزامی است',
            'code.required' => 'کد انبار الزامی است',
            'code.unique' => 'کد انبار تکراری است',
        ]);

        $warehouse = $this->service->update($id, $validated);

        return response()->json([
            'message' => 'انبار با موفقیت ویرایش شد',
            'data' => new WarehouseResource($warehouse),
        ]);
    }

    /**
     * حذف انبار
     */
    public function destroy(int $id)
    {
        $this->service->delete($id);

        return response()->json([
            'message' => 'انبار با موفقیت حذف شد',
        ]);
    }

    /**
     * لیست محل‌های یک انبار
     */
    public function locations($id)
    {
        $warehouseId = (int) $id;
        $warehouse = Warehouse::findOrFail($warehouseId);

        $locations = WarehouseLocation::active()
            ->where('warehouse_id', $warehouseId)
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $locations->map(fn ($l) => [
                'id' => $l->id,
                'name' => $l->name,
                'code' => $l->code,
                'section' => $l->section,
                'description' => $l->description,
            ]),
        ]);
    }
}
