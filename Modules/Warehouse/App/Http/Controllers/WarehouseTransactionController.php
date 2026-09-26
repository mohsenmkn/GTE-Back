<?php

// Modules/Inventory/App/Http/Controllers/WarehouseTransactionController.php

namespace Modules\Warehouse\App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\Warehouse\App\Models\WarehouseTransaction;
use Modules\Warehouse\App\Models\Material;
use Modules\Warehouse\Transformers\WarehouseTransactionResource;


class WarehouseTransactionController extends Controller
{
    /**
     * لیست تراکنش‌ها
     */
    public function index(Request $request)
    {
        $query = WarehouseTransaction::with(['material', 'project', 'wbsItem', 'contract', 'creator'])
            ->when($request->company_id, fn($q) => $q->where('company_id', 1))
            ->when($request->material_id, fn($q) => $q->where('material_id', $request->material_id))
            ->when($request->project_id, fn($q) => $q->where('project_id', $request->project_id))
            ->when($request->wbs_item_id, fn($q) => $q->where('wbs_item_id', $request->wbs_item_id))
            ->when($request->type, fn($q) => $q->where('type', $request->type))
            ->when($request->date_from, fn($q) => $q->whereDate('transaction_date', '>=', $request->date_from))
            ->when($request->date_to, fn($q) => $q->whereDate('transaction_date', '<=', $request->date_to))
            ->when($request->search, function ($q) use ($request) {
                $q->where(function ($query) use ($request) {
                    $query->where('reference_number', 'like', "%{$request->search}%")
                        ->orWhere('description', 'like', "%{$request->search}%");
                });
            })
            ->latest('transaction_date');

        return WarehouseTransactionResource::collection($query->paginate($request->per_page ?? 20));
    }

    /**
     * ایجاد تراکنش جدید
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'material_id' => 'required|exists:materials,id',
            'project_id' => 'nullable|exists:projects,id',
            'wbs_item_id' => 'nullable|exists:wbs_items,id',
            'contract_id' => 'nullable|exists:contracts,id',
            'type' => 'required|in:purchase,sale,transfer,adjustment,return,damage',
            'quantity' => 'required|numeric|min:0.01',
            'unit_price' => 'nullable|numeric|min:0',
            'total_price' => 'nullable|numeric|min:0',
            'transaction_date' => 'required|date',
            'reference_number' => 'nullable|string|max:100',
            'description' => 'nullable|string',
        ]);

        // محاسبه total_price
        if (!isset($data['total_price']) || $data['total_price'] == 0) {
            $data['total_price'] = $data['quantity'] * ($data['unit_price'] ?? 0);
        }

        $data['created_by'] = $request->user()->id;

        // بررسی موجودی برای تراکنش‌های خروجی
        if (in_array($data['type'], ['sale', 'transfer', 'damage'])) {
            $material = Material::find($data['material_id']);
            if ($material->current_stock < $data['quantity']) {
                return response()->json([
                    'message' => "موجودی کافی نیست. موجودی فعلی: {$material->current_stock}"
                ], 422);
            }
        }

        $transaction = WarehouseTransaction::create($data);

        return new WarehouseTransactionResource(
            $transaction->load(['material', 'project', 'wbsItem', 'contract', 'creator'])
        );
    }

    /**
     * نمایش یک تراکنش
     */
    public function show(WarehouseTransaction $transaction)
    {
        $transaction->load(['material', 'project', 'wbsItem', 'contract', 'creator']);
        return new WarehouseTransactionResource($transaction);
    }

    /**
     * حذف تراکنش
     */
    public function destroy(WarehouseTransaction $transaction)
    {
        // فقط تراکنش‌های ۲۴ ساعت اخیر قابل حذف هستند
        if ($transaction->created_at < now()->subHours(24)) {
            return response()->json([
                'message' => 'امکان حذف تراکنش‌های قدیمی‌تر از ۲۴ ساعت وجود ندارد.'
            ], 422);
        }

        // حذف تراکنش (موجودی به صورت خودکار برگردانده می‌شود)
        $transaction->delete();

        return response()->json(['message' => 'تراکنش با موفقیت حذف شد.'], 200);
    }

    /**
     * دریافت تراکنش‌های یک کالا
     */
    public function byMaterial($materialId, Request $request)
    {
        $material = Material::findOrFail($materialId);
        return $this->index($request->merge(['material_id' => $materialId]));
    }

    /**
     * دریافت تراکنش‌های یک پروژه
     */
    public function byProject($projectId, Request $request)
    {
        return $this->index($request->merge(['project_id' => $projectId]));
    }
}
