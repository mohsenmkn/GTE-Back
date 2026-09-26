<?php
// Modules/Inventory/App/Http/Controllers/InventoryReportController.php

namespace Modules\Warehouse\App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\Warehouse\App\Models\Material;
use Modules\Warehouse\App\Models\WarehouseTransaction;

class InventoryReportController extends Controller
{
    /**
     * گزارش موجودی انبار
     */
    public function stockReport(Request $request)
    {
        //$companyId = $request->user()->company_id;
        $companyId=1;
        $materials = Material::with(['category'])
            ->where('company_id', $companyId)
            ->when($request->category_id, fn($q) => $q->where('category_id', $request->category_id))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->get();

        return response()->json([
            'total_materials' => $materials->count(),
            'total_stock_value' => $materials->sum(function ($m) {
                return $m->current_stock * $m->unit_price;
            }),
            'materials' => $materials->map(function ($m) {
                return [
                    'id' => $m->id,
                    'code' => $m->code,
                    'name' => $m->name,
                    'category' => $m->category?->name,
                    'unit' => $m->unit,
                    'current_stock' => $m->current_stock,
                    'unit_price' => $m->unit_price,
                    'total_value' => $m->current_stock * $m->unit_price,
                    'min_stock' => $m->min_stock,
                    'max_stock' => $m->max_stock,
                    'status' => $m->status,
                ];
            }),
        ]);
    }

    /**
     * گزارش مصرف کلی
     */
    public function consumptionReport(Request $request)
    {
        //$companyId = $request->user()->company_id;
        $companyId=1;

        $startDate = $request->date_from ?? now()->subDays(30);
        $endDate = $request->date_to ?? now();

        $consumption = WarehouseTransaction::where('company_id', $companyId)
            ->whereIn('type', ['sale', 'transfer', 'damage'])
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->select(
                'material_id',
                DB::raw('SUM(quantity) as total_quantity'),
                DB::raw('SUM(total_price) as total_price')
            )
            ->groupBy('material_id')
            ->with('material')
            ->get();

        return response()->json([
            'period' => [
                'from' => $startDate,
                'to' => $endDate,
            ],
            'total_items' => $consumption->sum('total_quantity'),
            'total_value' => $consumption->sum('total_price'),
            'consumption' => $consumption->map(function ($item) {
                return [
                    'material_id' => $item->material_id,
                    'material_name' => $item->material?->name,
                    'material_code' => $item->material?->code,
                    'unit' => $item->material?->unit,
                    'quantity' => $item->total_quantity,
                    'total_price' => $item->total_price,
                ];
            }),
        ]);
    }

    /**
     * گزارش مصرف یک پروژه
     */
    public function projectConsumption($projectId, Request $request)
    {
        $transactions = WarehouseTransaction::where('project_id', $projectId)
            ->with(['material', 'wbsItem'])
            ->when($request->date_from, fn($q) => $q->whereDate('transaction_date', '>=', $request->date_from))
            ->when($request->date_to, fn($q) => $q->whereDate('transaction_date', '<=', $request->date_to))
            ->get();

        $summary = $transactions->groupBy('material_id')
            ->map(function ($items) {
                $material = $items->first()->material;
                return [
                    'material_id' => $material?->id,
                    'material_name' => $material?->name,
                    'unit' => $material?->unit,
                    'total_quantity' => $items->sum('quantity'),
                    'total_price' => $items->sum('total_price'),
                    'transactions_count' => $items->count(),
                    'by_wbs' => $items->groupBy('wbs_item_id')->map(function ($wbsItems) {
                        $wbs = $wbsItems->first()->wbsItem;
                        return [
                            'wbs_id' => $wbs?->id,
                            'wbs_name' => $wbs?->name,
                            'quantity' => $wbsItems->sum('quantity'),
                            'total_price' => $wbsItems->sum('total_price'),
                        ];
                    }),
                ];
            });

        return response()->json([
            'project_id' => $projectId,
            'total_transactions' => $transactions->count(),
            'total_quantity' => $transactions->sum('quantity'),
            'total_price' => $transactions->sum('total_price'),
            'summary' => $summary->values(),
        ]);
    }

    /**
     * گزارش مصرف یک WBS Item
     */
    public function wbsConsumption($wbsItemId, Request $request)
    {
        $transactions = WarehouseTransaction::where('wbs_item_id', $wbsItemId)
            ->with(['material'])
            ->when($request->date_from, fn($q) => $q->whereDate('transaction_date', '>=', $request->date_from))
            ->when($request->date_to, fn($q) => $q->whereDate('transaction_date', '<=', $request->date_to))
            ->get();

        return response()->json([
            'wbs_item_id' => $wbsItemId,
            'total_transactions' => $transactions->count(),
            'total_quantity' => $transactions->sum('quantity'),
            'total_price' => $transactions->sum('total_price'),
            'consumption' => $transactions->groupBy('material_id')->map(function ($items) {
                $material = $items->first()->material;
                return [
                    'material_id' => $material?->id,
                    'material_name' => $material?->name,
                    'unit' => $material?->unit,
                    'quantity' => $items->sum('quantity'),
                    'total_price' => $items->sum('total_price'),
                ];
            })->values(),
        ]);
    }

    /**
     * گزارش حرکت کالاها (ورود/خروج)
     */
    public function movementReport(Request $request)
    {
        //$companyId = $request->user()->company_id;
        $companyId=1;

        $startDate = $request->date_from ?? now()->subDays(30);
        $endDate = $request->date_to ?? now();

        $transactions = WarehouseTransaction::where('company_id', $companyId)
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->select(
                'material_id',
                'type',
                DB::raw('SUM(quantity) as total_quantity'),
                DB::raw('SUM(total_price) as total_price')
            )
            ->groupBy('material_id', 'type')
            ->with('material')
            ->get();

        return response()->json([
            'period' => [
                'from' => $startDate,
                'to' => $endDate,
            ],
            'transactions' => $transactions->groupBy('material_id')->map(function ($items) {
                $material = $items->first()->material;
                $incoming = $items->whereIn('type', ['purchase', 'return'])->sum('total_quantity');
                $outgoing = $items->whereIn('type', ['sale', 'transfer', 'damage'])->sum('total_quantity');

                return [
                    'material_id' => $material?->id,
                    'material_name' => $material?->name,
                    'unit' => $material?->unit,
                    'incoming' => $incoming,
                    'outgoing' => $outgoing,
                    'net_movement' => $incoming - $outgoing,
                    'details' => $items->map(function ($item) {
                        return [
                            'type' => $item->type,
                            'quantity' => $item->total_quantity,
                            'total_price' => $item->total_price,
                        ];
                    }),
                ];
            })->values(),
        ]);
    }

    /**
     * گزارش کالاهای با موجودی کم
     */
    public function lowStockReport(Request $request)
    {
        //$companyId = $request->user()->company_id;
        $companyId=1;

        $materials = Material::where('company_id', $companyId)
            ->where('status', 'active')
            ->whereColumn('current_stock', '<=', 'min_stock')
            ->with(['category'])
            ->get();

        return response()->json([
            'total_low_stock_items' => $materials->count(),
            'materials' => $materials->map(function ($m) {
                return [
                    'id' => $m->id,
                    'code' => $m->code,
                    'name' => $m->name,
                    'category' => $m->category?->name,
                    'current_stock' => $m->current_stock,
                    'min_stock' => $m->min_stock,
                    'max_stock' => $m->max_stock,
                    'unit' => $m->unit,
                    'unit_price' => $m->unit_price,
                    'shortage' => $m->min_stock - $m->current_stock,
                ];
            }),
        ]);
    }

    public function projectMaterials($projectId, Request $request)
    {
        $companyId = 1;

        // دریافت تمام کالاهایی که برای این پروژه تراکنش دارند
        $materials = Material::with(['category', 'transactions' => function($q) use ($projectId) {
            $q->where('project_id', $projectId)
                ->whereIn('type', ['sale', 'transfer', 'damage']);
        }])
            ->where('company_id', $companyId)
            ->whereHas('transactions', function($q) use ($projectId) {
                $q->where('project_id', $projectId)
                    ->whereIn('type', ['sale', 'transfer', 'damage']);
            })
            ->when($request->category_id, fn($q) => $q->where('category_id', $request->category_id))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->search, function($q) use ($request) {
                $q->where(function($query) use ($request) {
                    $query->where('name', 'like', "%{$request->search}%")
                        ->orWhere('code', 'like', "%{$request->search}%");
                });
            })
            ->get()
            ->map(function ($material) use ($projectId) {
                // محاسبه مصرف پروژه
                $consumed = $material->transactions->sum('quantity');

                return [
                    'id' => $material->id,
                    'code' => $material->code,
                    'name' => $material->name,
                    'unit' => $material->unit,
                    'unit_price' => $material->unit_price,
                    'current_stock' => $material->current_stock,
                    'min_stock' => $material->min_stock,
                    'max_stock' => $material->max_stock,
                    'consumed_quantity' => $consumed,
                    'category' => $material->category ? [
                        'id' => $material->category->id,
                        'name' => $material->category->name,
                    ] : null,
                    'status' => $material->status,
                    'transactions_count' => $material->transactions->count(),
                ];
            });

        return response()->json([
            'data' => $materials,
            'meta' => [
                'total' => $materials->count(),
            ]
        ]);
    }
}
