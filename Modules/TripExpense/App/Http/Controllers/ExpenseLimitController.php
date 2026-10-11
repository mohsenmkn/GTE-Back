<?php

namespace Modules\TripExpense\App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\TripExpense\App\Http\Requests\ExpenseLimitRequest;
use Modules\TripExpense\App\Http\Resources\ExpenseLimitResource;
use Modules\TripExpense\App\Models\ExpenseLimit;

class ExpenseLimitController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ExpenseLimit::with(['category', 'creator']);

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('limit_type')) {
            $query->where('limit_type', $request->limit_type);
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $limits = $query->orderByDesc('effective_from')
            ->paginate($request->get('per_page', 15));

        return response()->json(ExpenseLimitResource::collection($limits));
    }

    public function store(ExpenseLimitRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['created_by'] = auth()->id();

        $limit = ExpenseLimit::create($data);

        return response()->json([
            'message' => 'سقف هزینه با موفقیت ایجاد شد',
            'data' => new ExpenseLimitResource($limit->load(['category', 'creator'])),
        ], 201);
    }

    public function show(ExpenseLimit $expenseLimit): JsonResponse
    {
        return response()->json(
            new ExpenseLimitResource($expenseLimit->load(['category', 'creator', 'updater']))
        );
    }

    public function update(ExpenseLimitRequest $request, ExpenseLimit $expenseLimit): JsonResponse
    {
        $data = $request->validated();
        $data['updated_by'] = auth()->id();

        $expenseLimit->update($data);

        return response()->json([
            'message' => 'سقف هزینه با موفقیت ویرایش شد',
            'data' => new ExpenseLimitResource($expenseLimit->load(['category', 'creator'])),
        ]);
    }

    public function destroy(ExpenseLimit $expenseLimit): JsonResponse
    {
        $expenseLimit->delete();

        return response()->json([
            'message' => 'سقف هزینه با موفقیت حذف شد',
        ]);
    }
}
