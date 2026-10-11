<?php

namespace Modules\TripExpense\App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Modules\TripExpense\App\Http\Requests\TripExpenseRequest;
use Modules\TripExpense\App\Http\Resources\TripExpenseResource;
use Modules\TripExpense\App\Models\ExpenseCategory;
use Modules\TripExpense\App\Models\Trip;
use Modules\TripExpense\App\Models\TripExpense;
use Modules\TripExpense\App\Services\ExpenseLimitService;

class TripExpenseController extends Controller
{
    protected ExpenseLimitService $limitService;

    public function __construct(ExpenseLimitService $limitService)
    {
        $this->limitService = $limitService;
    }


    public function index(Trip $trip, Request $request): JsonResponse
    {
        // راننده فقط می‌تواند هزینه‌های سفرهای خودش را ببیند
        if (auth()->user()->hasRole('driver') && $trip->driver_id !== auth()->id()) {
            abort(403, 'دسترسی غیرمجاز');
        }

        // ✅ اصلاح: eager loading
        $expenses = $trip->expenses()
            ->with([
                'category:id,name,code',
                'trip:id,driver_id,truck_id,origin,destination',
                'trip.driver:id,name,personnel_code',
                'trip.truck:id,code,plate_number',
            ])
            ->orderByDesc('expense_date')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => TripExpenseResource::collection($expenses),
            'meta' => [
                'current_page' => $expenses->currentPage(),
                'per_page' => $expenses->perPage(),
                'total' => $expenses->total(),
                'last_page' => $expenses->lastPage(),
            ],
        ]);
    }

    public function store(TripExpenseRequest $request, Trip $trip): JsonResponse
    {
        // راننده فقط می‌تواند در سفرهای خودش هزینه ثبت کند
        if (auth()->user()->hasRole('driver') && $trip->driver_id !== auth()->id()) {
            abort(403, 'دسترسی غیرمجاز');
        }

        // فقط در سفر در حال انجام می‌توان هزینه ثبت کرد
        if (!$trip->isInProgress()) {
            return response()->json([
                'message' => 'فقط در سفرهای در حال انجام می‌توان هزینه ثبت کرد',
            ], 422);
        }

        $data = $request->validated();
        $category = ExpenseCategory::findOrFail($data['category_id']);

        // آپلود تصویر رسید
        $receiptPath = $request->file('receipt')->store('trip-expenses/receipts', 'public');

        // بررسی سقف
        $limitCheck = $this->limitService->checkLimit($trip, $category, $data['amount']);

        // ایجاد هزینه
        $expense = TripExpense::create([
            'trip_id' => $trip->id,
            'category_id' => $category->id,
            'amount' => $data['amount'],
            'receipt_path' => $receiptPath,
            'expense_date' => $data['expense_date'],
            'description' => $data['description'] ?? null,
            'status' => 'pending',
            'exceeded_limit' => $limitCheck['exceeded'],
            'warning_message' => $limitCheck['warning_message'],
        ]);

        $message = $limitCheck['exceeded']
            ? 'هزینه ثبت شد اما از سقف مجاز تجاوز کرده است و نیاز به تایید مدیر دارد'
            : 'هزینه با موفقیت ثبت شد و منتظر تایید مدیر است';

        return response()->json([
            'message' => $message,
            'data' => new TripExpenseResource($expense->load(['category'])),
            'limit_info' => $limitCheck,
        ], 201);
    }

    public function destroy(TripExpense $expense): JsonResponse
    {
        // راننده فقط می‌تواند هزینه‌های خودش را حذف کند
        if (auth()->user()->hasRole('driver')) {
            if ($expense->trip->driver_id !== auth()->id()) {
                abort(403, 'دسترسی غیرمجاز');
            }

            // فقط هزینه‌های pending قابل حذف هستند
            if (!$expense->isPending()) {
                return response()->json([
                    'message' => 'فقط هزینه‌های در انتظار تایید قابل حذف هستند',
                ], 422);
            }
        }

        // حذف تصویر رسید
        Storage::disk('public')->delete($expense->receipt_path);

        $expense->delete();

        return response()->json([
            'message' => 'هزینه با موفقیت حذف شد',
        ]);
    }

    public function receipt(TripExpense $expense)
    {
        // راننده فقط می‌تواند رسیدهای خودش را ببیند
        if (auth()->user()->hasRole('driver') && $expense->trip->driver_id !== auth()->id()) {
            abort(403, 'دسترسی غیرمجاز');
        }

        if (!Storage::disk('public')->exists($expense->receipt_path)) {
            abort(404, 'تصویر رسید یافت نشد');
        }

        return Storage::disk('public')->response($expense->receipt_path);
    }

    public function remainingBudget(Trip $trip, Request $request): JsonResponse
    {
        // راننده فقط می‌تواند سفرهای خودش را ببیند
        if (auth()->user()->hasRole('driver') && $trip->driver_id !== auth()->id()) {
            abort(403, 'دسترسی غیرمجاز');
        }

        $categoryId = $request->get('category_id');

        if ($categoryId) {
            $category = ExpenseCategory::findOrFail($categoryId);
            $budget = $this->limitService->getRemainingBudget($trip, $category);
        } else {
            // محاسبه برای همه دسته‌ها
            $budget = [];
            $categories = ExpenseCategory::active()->get();
            foreach ($categories as $category) {
                $budget[$category->code] = $this->limitService->getRemainingBudget($trip, $category);
            }
        }

        return response()->json([
            'data' => $budget,
        ]);
    }

    public function approve(TripExpense $expense, Request $request): JsonResponse
    {
        if ($expense->isApproved()) {
            return response()->json([
                'message' => 'این هزینه قبلاً تایید شده است',
            ], 422);
        }

        $expense->update([
            'status' => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        return response()->json([
            'message' => 'هزینه با موفقیت تایید شد',
            'data' => new TripExpenseResource($expense),
        ]);
    }

    public function reject(TripExpense $expense, Request $request): JsonResponse
    {
        $request->validate([
            'rejection_reason' => 'required|string|max:500',
        ]);

        if ($expense->isRejected()) {
            return response()->json([
                'message' => 'این هزینه قبلاً رد شده است',
            ], 422);
        }

        $expense->update([
            'status' => 'rejected',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
            'rejection_reason' => $request->rejection_reason,
        ]);

        return response()->json([
            'message' => 'هزینه با موفقیت رد شد',
            'data' => new TripExpenseResource($expense),
        ]);
    }


    // Modules/TripExpense/App/Http/Controllers/TripExpenseController.php

// ✅ اضافه کردن متد جدید برای لیست همه هزینه‌ها (برای مدیر)
// Modules/TripExpense/App/Http/Controllers/TripExpenseController.php

    // Modules/TripExpense/App/Http/Controllers/TripExpenseController.php

    public function indexAll(Request $request): JsonResponse
    {
        $query = TripExpense::query();

        // ✅ اصلاح: eager loading با load() به جای with()
        $query->with([
            'trip',
            'trip.driver',
            'trip.truck',
            'category',
        ]);

        // فیلتر وضعیت
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // فیلتر تجاوز از سقف
        if ($request->filled('exceeded_limit')) {
            $query->where('exceeded_limit', $request->boolean('exceeded_limit'));
        }

        // فیلتر دسته‌بندی
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // فیلتر راننده
        if ($request->filled('driver_id')) {
            $query->whereHas('trip', function ($q) use ($request) {
                $q->where('driver_id', $request->driver_id);
            });
        }

        // فیلتر تاریخ
        if ($request->filled('from_date')) {
            $query->where('expense_date', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->where('expense_date', '<=', $request->to_date);
        }

        $expenses = $query->orderByDesc('expense_date')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => TripExpenseResource::collection($expenses),
            'meta' => [
                'current_page' => $expenses->currentPage(),
                'per_page' => $expenses->perPage(),
                'total' => $expenses->total(),
                'last_page' => $expenses->lastPage(),
            ],
        ]);
    }
}
