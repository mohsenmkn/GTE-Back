<?php

namespace Modules\PreWarehouse\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\PreWarehouse\App\Models\WarehouseLocation; // ✅ Model درست

class WarehouseLocationController extends Controller
{
    /**
     * لیست محل‌های یک انبار
     */
    public function index(int $warehouseId)
    {
        $locations = WarehouseLocation::where('warehouse_id', $warehouseId)
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $locations->map(fn ($l) => [
                'id' => $l->id,
                'name' => $l->name,
                'code' => $l->code,
                'section' => $l->section,
                'description' => $l->description,
                'is_active' => $l->is_active,
            ]),
        ]);
    }

    /**
     * ثبت محل جدید
     */
    public function store(Request $request, int $warehouseId)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
            'section' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
        ], [
            'name.required' => 'نام محل الزامی است',
        ]);

        // بررسی یکتایی کد در انبار
        if (!empty($validated['code'])) {
            $exists = WarehouseLocation::where('warehouse_id', $warehouseId)
                ->where('code', $validated['code'])
                ->exists();
            if ($exists) {
                return response()->json([
                    'message' => 'کد محل در این انبار تکراری است',
                ], 422);
            }
        }

        // ✅ استفاده از WarehouseLocation به جای Location
        $location = WarehouseLocation::create(array_merge($validated, [
            'warehouse_id' => $warehouseId,
            'is_active' => $validated['is_active'] ?? true,
        ]));

        return response()->json([
            'message' => 'محل با موفقیت ثبت شد',
            'data' => [
                'id' => $location->id,
                'name' => $location->name,
                'code' => $location->code,
                'section' => $location->section,
                'description' => $location->description,
                'is_active' => $location->is_active,
            ],
        ], 201);
    }

    /**
     * ویرایش محل
     */
    public function update(Request $request, int $warehouseId, int $id)
    {
        $location = WarehouseLocation::where('warehouse_id', $warehouseId)
            ->findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
            'section' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
        ], [
            'name.required' => 'نام محل الزامی است',
        ]);

        if (!empty($validated['code'])) {
            $exists = WarehouseLocation::where('warehouse_id', $warehouseId)
                ->where('code', $validated['code'])
                ->where('id', '!=', $id)
                ->exists();
            if ($exists) {
                return response()->json([
                    'message' => 'کد محل در این انبار تکراری است',
                ], 422);
            }
        }

        $location->update($validated);

        return response()->json([
            'message' => 'محل با موفقیت ویرایش شد',
            'data' => [
                'id' => $location->id,
                'name' => $location->name,
                'code' => $location->code,
                'section' => $location->section,
                'description' => $location->description,
                'is_active' => $location->is_active,
            ],
        ]);
    }

    /**
     * حذف محل
     */
    public function destroy(int $warehouseId, int $id)
    {
        $location = WarehouseLocation::where('warehouse_id', $warehouseId)
            ->findOrFail($id);

        $location->delete();

        return response()->json([
            'message' => 'محل با موفقیت حذف شد',
        ]);
    }
}
