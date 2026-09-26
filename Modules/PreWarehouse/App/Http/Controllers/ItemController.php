<?php

namespace Modules\PreWarehouse\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\PreWarehouse\App\Http\Resources\ItemResource;
use Modules\PreWarehouse\App\Models\Item;
use Modules\PreWarehouse\App\Services\ItemService;

class ItemController extends Controller
{
    protected $service;

    public function __construct(ItemService $service)
    {
        $this->service = $service;
    }

    /**
     * لیست کالاها
     */
    public function index(Request $request)
    {
        $filters = $request->only(['search', 'per_page', 'is_active']);
        $items = $this->service->getAll($filters);

        return response()->json([
            'data' => ItemResource::collection($items),
            'meta' => [
                'total' => $items->total(),
                'per_page' => $items->perPage(),
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
            ],
        ]);
    }

    /**
     * لیست همه کالاها (برای Select)
     */
    public function all(Request $request)
    {
        $query = Item::query()->active()->orderBy('name');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $items = $query->limit(100)->get();

        return response()->json([
            'data' => $items->map(fn ($item) => [
                'id' => $item->id,
                'name' => $item->name,
                'code' => $item->code,
                'unit_of_measurement' => $item->unit_of_measurement,
            ]),
        ]);
    }

    /**
     * نمایش جزئیات
     */
    public function show(int $id)
    {
        $item = Item::findOrFail($id);

        return response()->json([
            'data' => new ItemResource($item),
        ]);
    }

    /**
     * ثبت کالای جدید
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50|unique:pre_warehouse_items,code',
            'description' => 'nullable|string|max:1000',
            'unit_of_measurement' => 'required|string|max:50',
            'is_active' => 'boolean',
        ], [
            'name.required' => 'نام کالا الزامی است',
            'unit_of_measurement.required' => 'واحد اندازه‌گیری الزامی است',
            'code.unique' => 'کد کالا تکراری است',
        ]);

        $item = $this->service->create($validated);

        return response()->json([
            'message' => 'کالا با موفقیت ثبت شد',
            'data' => new ItemResource($item),
        ], 201);
    }

    /**
     * ویرایش کالا
     */
    public function update(Request $request, int $id)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50|unique:pre_warehouse_items,code,' . $id,
            'description' => 'nullable|string|max:1000',
            'unit_of_measurement' => 'required|string|max:50',
            'is_active' => 'boolean',
        ], [
            'name.required' => 'نام کالا الزامی است',
            'unit_of_measurement.required' => 'واحد اندازه‌گیری الزامی است',
            'code.unique' => 'کد کالا تکراری است',
        ]);

        $item = $this->service->update($id, $validated);

        return response()->json([
            'message' => 'کالا با موفقیت ویرایش شد',
            'data' => new ItemResource($item),
        ]);
    }

    /**
     * حذف کالا
     */
    public function destroy(int $id)
    {
        $this->service->delete($id);

        return response()->json([
            'message' => 'کالا با موفقیت حذف شد',
        ]);
    }
}
