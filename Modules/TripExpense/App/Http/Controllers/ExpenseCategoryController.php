<?php

namespace Modules\TripExpense\App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\TripExpense\App\Http\Requests\ExpenseCategoryRequest;
use Modules\TripExpense\App\Http\Resources\ExpenseCategoryResource;
use Modules\TripExpense\App\Models\ExpenseCategory;

class ExpenseCategoryController extends Controller
{
    /**
     * لیست دسته‌بندی‌ها (برای مدیر - با امکان فیلتر و pagination)
     */
    public function index(Request $request): JsonResponse
    {
        $query = ExpenseCategory::query();

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $categories = $query->ordered()->paginate($request->get('per_page', 15));

        return response()->json(ExpenseCategoryResource::collection($categories));
    }

    /**
     * لیست دسته‌بندی‌های فعال (برای همه - جهت استفاده در فرم‌ها)
     */
    public function publicIndex(): JsonResponse
    {
        $categories = ExpenseCategory::active()
            ->ordered()
            ->get(['id', 'name', 'code']);

        return response()->json([
            'data' => ExpenseCategoryResource::collection($categories),
        ]);
    }

    public function store(ExpenseCategoryRequest $request): JsonResponse
    {
        $category = ExpenseCategory::create($request->validated());

        return response()->json([
            'message' => 'دسته‌بندی با موفقیت ایجاد شد',
            'data' => new ExpenseCategoryResource($category),
        ], 201);
    }

    public function show(ExpenseCategory $expenseCategory): JsonResponse
    {
        return response()->json(new ExpenseCategoryResource($expenseCategory));
    }

    public function update(ExpenseCategoryRequest $request, ExpenseCategory $expenseCategory): JsonResponse
    {
        $expenseCategory->update($request->validated());

        return response()->json([
            'message' => 'دسته‌بندی با موفقیت ویرایش شد',
            'data' => new ExpenseCategoryResource($expenseCategory),
        ]);
    }

    public function destroy(ExpenseCategory $expenseCategory): JsonResponse
    {
        // بررسی اینکه آیا این دسته‌بندی در هزینه‌ها استفاده شده یا نه
        if ($expenseCategory->expenses()->exists()) {
            return response()->json([
                'message' => 'این دسته‌بندی در هزینه‌ها استفاده شده و قابل حذف نیست. می‌توانید آن را غیرفعال کنید.',
            ], 422);
        }

        $expenseCategory->delete();

        return response()->json([
            'message' => 'دسته‌بندی با موفقیت حذف شد',
        ]);
    }
}
