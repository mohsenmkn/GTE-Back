<?php
// Modules/Inventory/App/Http/Controllers/MaterialController.php

namespace Modules\Warehouse\App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Warehouse\App\Models\Material;
use Modules\Warehouse\Transformers\MaterialResource;
use Modules\Warehouse\Transformers\WarehouseTransactionResource;

class MaterialController extends Controller
{
    /**
     * لیست کالاها
     */
    public function index(Request $request)
    {
        $query = Material::with(['category'])
            ->when($request->company_id, fn($q) => $q->where('company_id', 1))
            ->when($request->category_id, fn($q) => $q->where('category_id', $request->category_id))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->low_stock, fn($q) => $q->lowStock())
            ->when($request->search, function($q) use ($request) {
                $q->where(function($query) use ($request) {
                    $query->where('name', 'like', "%{$request->search}%")
                        ->orWhere('code', 'like', "%{$request->search}%");
                });
            })
            ->when($request->sort_by, function($q) use ($request) {
                $sortOrder = $request->sort_order ?? 'asc';
                $q->orderBy($request->sort_by, $sortOrder);
            });

        return MaterialResource::collection($query->paginate($request->per_page ?? 15));
    }

    /**
     * ایجاد کالا جدید
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'category_id' => 'nullable|exists:inventory_categories,id',
            'code' => 'required|string|max:50|unique:materials',
            'name' => 'required|string|max:255',
            'unit' => 'nullable|string|max:50',
            'unit_price' => 'nullable|numeric|min:0',
            'current_stock' => 'nullable|numeric|min:0',
            'min_stock' => 'nullable|numeric|min:0',
            'max_stock' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
            'status' => 'required|in:active,inactive',
        ]);

        $material = Material::create($data);
        return new MaterialResource($material->load('category'));
    }

    /**
     * نمایش یک کالا
     */
    public function show(Material $material)
    {
        $material->load(['category', 'transactions']);
        return new MaterialResource($material);
    }

    /**
     * ویرایش کالا
     */
    public function update(Request $request, Material $material)
    {
        $data = $request->validate([
            'category_id' => 'nullable|exists:inventory_categories,id',
            'code' => 'sometimes|required|string|max:50|unique:materials,code,' . $material->id,
            'name' => 'sometimes|required|string|max:255',
            'unit' => 'nullable|string|max:50',
            'unit_price' => 'nullable|numeric|min:0',
            'current_stock' => 'nullable|numeric|min:0',
            'min_stock' => 'nullable|numeric|min:0',
            'max_stock' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
            'status' => 'sometimes|required|in:active,inactive',
        ]);

        $material->update($data);
        return new MaterialResource($material->load('category'));
    }

    /**
     * حذف کالا
     */
    public function destroy(Material $material)
    {
        if ($material->transactions()->exists()) {
            return response()->json([
                'message' => 'این کالا دارای تراکنش است و قابل حذف نیست.'
            ], 422);
        }
        $material->delete();
        return response()->json(['message' => 'کالا با موفقیت حذف شد.'], 200);
    }

    /**
     * دریافت تراکنش‌های یک کالا
     */
    public function transactions(Material $material, Request $request)
    {
        $transactions = $material->transactions()
            ->with(['project', 'wbsItem', 'creator'])
            ->when($request->type, fn($q) => $q->where('type', $request->type))
            ->when($request->date_from, fn($q) => $q->whereDate('transaction_date', '>=', $request->date_from))
            ->when($request->date_to, fn($q) => $q->whereDate('transaction_date', '<=', $request->date_to))
            ->latest('transaction_date')
            ->paginate($request->per_page ?? 20);

        return WarehouseTransactionResource::collection($transactions);
    }

    /**
     * دریافت تاریخچه موجودی کالا
     */
    public function stockHistory(Material $material, Request $request)
    {
        $days = $request->days ?? 30;
        $startDate = now()->subDays($days);

        // محاسبه موجودی در طول زمان
        // این یک پیاده‌سازی ساده است - می‌توانید پیچیده‌تر کنید
        $history = $material->transactions()
            ->where('transaction_date', '>=', $startDate)
            ->orderBy('transaction_date')
            ->get()
            ->groupBy('transaction_date')
            ->map(function ($items, $date) use ($material) {
                $netChange = $items->sum(function ($item) {
                    return in_array($item->type, ['purchase', 'return'])
                        ? $item->quantity
                        : -$item->quantity;
                });
                return [
                    'date' => $date,
                    'quantity' => $netChange,
                ];
            });

        return response()->json([
            'material_id' => $material->id,
            'material_name' => $material->name,
            'current_stock' => $material->current_stock,
            'history' => $history->values(),
        ]);
    }
}
