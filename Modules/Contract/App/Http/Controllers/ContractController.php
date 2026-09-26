<?php
// Modules/Contract/App/Http/Controllers/ContractController.php

namespace Modules\Contract\App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\Contract\App\Models\Contract;
use Modules\Contract\App\Http\Requests\StoreContractRequest;
use Modules\Contract\Transformers\ContractResource;
use Modules\WBS\App\Models\WbsItem;

class ContractController extends Controller
{
    /**
     * لیست قراردادها
     */
    public function index(Request $request)
    {
        $query = Contract::with(['project', 'contractor', 'creator', 'wbsItems'])
            ->when($request->company_id, fn($q) => $q->where('company_id', $request->company_id))
            ->when($request->project_id, fn($q) => $q->where('project_id', $request->project_id))
            ->when($request->contractor_id, fn($q) => $q->where('contractor_id', $request->contractor_id))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->type, fn($q) => $q->where('type', $request->type))
            ->when($request->search, function($q) use ($request) {
                $q->where(function($query) use ($request) {
                    $query->where('title', 'like', "%{$request->search}%")
                        ->orWhere('contract_number', 'like', "%{$request->search}%");
                });
            })
            ->when($request->date_from, fn($q) => $q->whereDate('created_at', '>=', $request->date_from))
            ->when($request->date_to, fn($q) => $q->whereDate('created_at', '<=', $request->date_to))
            ->latest();

        return ContractResource::collection($query->paginate($request->per_page ?? 15));
    }

    /**
     * ایجاد قرارداد جدید
     */
    public function store(StoreContractRequest $request)
    {
        return DB::transaction(function () use ($request) {
            $data = $request->validated();
            $wbsItems = $data['wbs_items'] ?? [];
            unset($data['wbs_items']);

            // ایجاد قرارداد
            $data['created_by'] = $request->user()->id;
            $contract = Contract::create($data);

            // اتصال به WBS Items
            if (!empty($wbsItems)) {
                $syncData = [];
                foreach ($wbsItems as $item) {
                    $totalPrice = ($item['quantity'] ?? 0) * ($item['unit_price'] ?? 0);
                    $syncData[$item['id']] = [
                        'quantity' => $item['quantity'] ?? 0,
                        'unit_price' => $item['unit_price'] ?? 0,
                        'total_price' => $totalPrice,
                        'description' => $item['description'] ?? null,
                    ];
                }
                $contract->wbsItems()->sync($syncData);
            }

            $contract->load(['project', 'contractor', 'creator', 'wbsItems']);

            return new ContractResource($contract);
        });
    }

    /**
     * نمایش یک قرارداد
     */
    public function show(Contract $contract)
    {
        $contract->load(['project', 'contractor', 'creator', 'wbsItems', 'documents']);
        return new ContractResource($contract);
    }

    /**
     * ویرایش قرارداد
     */
    public function update(Request $request, Contract $contract)
    {
        return DB::transaction(function () use ($request, $contract) {
            $data = $request->validate([
                'contractor_id' => 'sometimes|exists:contractors,id',
                'contract_number' => 'nullable|string|max:50|unique:contracts,contract_number,' . $contract->id,
                'title' => 'sometimes|required|string|max:255',
                'type' => 'sometimes|required|in:construction,service,consulting,supply,other',
                'amount' => 'sometimes|required|numeric|min:0',
                'paid_amount' => 'nullable|numeric|min:0',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'status' => 'sometimes|required|in:draft,pending,active,completed,cancelled,suspended',
                'description' => 'nullable|string',
                'terms' => 'nullable|string',
                'wbs_items' => 'nullable|array',
                'wbs_items.*.id' => 'required|exists:wbs_items,id',
                'wbs_items.*.quantity' => 'nullable|numeric|min:0',
                'wbs_items.*.unit_price' => 'nullable|numeric|min:0',
                'wbs_items.*.description' => 'nullable|string',
            ]);

            $wbsItems = $data['wbs_items'] ?? null;
            unset($data['wbs_items']);

            $contract->update($data);

            // به‌روزرسانی WBS Items
            if ($wbsItems !== null) {
                $syncData = [];
                foreach ($wbsItems as $item) {
                    $totalPrice = ($item['quantity'] ?? 0) * ($item['unit_price'] ?? 0);
                    $syncData[$item['id']] = [
                        'quantity' => $item['quantity'] ?? 0,
                        'unit_price' => $item['unit_price'] ?? 0,
                        'total_price' => $totalPrice,
                        'description' => $item['description'] ?? null,
                    ];
                }
                $contract->wbsItems()->sync($syncData);
            }

            $contract->load(['project', 'contractor', 'creator', 'wbsItems']);

            return new ContractResource($contract);
        });
    }

    /**
     * حذف قرارداد
     */
    public function destroy(Contract $contract)
    {
        if ($contract->status === 'active') {
            return response()->json([
                'message' => 'قرارداد فعال قابل حذف نیست. ابتدا آن را لغو کنید.'
            ], 422);
        }

        $contract->delete();

        return response()->json(['message' => 'قرارداد با موفقیت حذف شد.'], 200);
    }

    /**
     * تغییر وضعیت قرارداد
     */
    public function changeStatus(Request $request, Contract $contract)
    {
        $request->validate([
            'status' => 'required|in:draft,pending,active,completed,cancelled,suspended',
        ]);

        $contract->update(['status' => $request->status]);

        return new ContractResource($contract->load(['project', 'contractor', 'creator', 'wbsItems']));
    }

    /**
     * دریافت خلاصه مالی قرارداد
     */
    public function summary(Contract $contract)
    {
        $totalWbsPrice = $contract->wbsItems()->sum('total_price');

        return response()->json([
            'contract_id' => $contract->id,
            'contract_number' => $contract->contract_number,
            'title' => $contract->title,
            'total_amount' => $contract->amount,
            'paid_amount' => $contract->paid_amount,
            'remaining_amount' => $contract->remaining_amount,
            'progress_percent' => $contract->progress_percent,
            'wbs_total_price' => $totalWbsPrice,
            'status' => $contract->status,
        ]);
    }
}
