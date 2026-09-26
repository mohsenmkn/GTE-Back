<?php

namespace Modules\PettyCash\App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\PettyCash\App\Models\PettyCash;
use Modules\PettyCash\App\Http\Requests\StorePettyCashRequest;
use Modules\PettyCash\Transformers\PettyCashResource;

class PettyCashController extends Controller
{
    public function index(Request $request)
    {
        $query = PettyCash::query()
            ->withCount('transactions')
            ->when($request->project_id, fn($q) => $q->where('project_id', $request->project_id))
            ->when($request->is_active !== null, fn($q) => $q->where('is_active', $request->is_active));

        return PettyCashResource::collection($query->paginate($request->per_page ?? 15));
    }

    public function store(StorePettyCashRequest $request)
    {
        // ✅ بررسی تنخواه فعال با is_active
        $exists = PettyCash::where('project_id', $request->project_id)
            ->where('is_active', true)
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'این پروژه قبلاً تنخواه فعال دارد.'], 422);
        }

        $data = $request->validated();

        // ✅ تنظیم مقادیر پیش‌فرض
        $data['name'] = $data['name'] ?? 'تنخواه گردان';
        $data['current_balance'] = $data['current_balance'] ?? $data['initial_amount'];
        $data['is_active'] = $data['is_active'] ?? true;

        $pettyCash = PettyCash::create($data);

        return new PettyCashResource($pettyCash);
    }

    public function show(PettyCash $pettyCash)
    {
        $pettyCash->load(['transactions']);
        return new PettyCashResource($pettyCash);
    }

    public function update(Request $request, PettyCash $pettyCash)
    {
        $data = $request->validate([
            'name'            => 'nullable|string|max:255',
            'initial_amount'  => 'sometimes|numeric|min:0',
            'current_balance' => 'sometimes|numeric|min:0',
            'is_active'       => 'sometimes|boolean',
        ]);

        $pettyCash->update($data);

        return new PettyCashResource($pettyCash->fresh());
    }

    public function destroy(PettyCash $pettyCash)
    {
        if ($pettyCash->transactions()->exists()) {
            return response()->json(['message' => 'تنخواه دارای تراکنش است و قابل حذف نیست.'], 422);
        }

        $pettyCash->delete();

        return response()->json(['message' => 'تنخواه حذف شد.'], 200);
    }
}
