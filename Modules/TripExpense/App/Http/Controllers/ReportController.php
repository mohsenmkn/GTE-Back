<?php
namespace Modules\TripExpense\App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\TripExpense\App\Models\ExpenseCategory;
use Modules\TripExpense\App\Models\Trip;
use Modules\TripExpense\App\Models\TripExpense;
use Modules\TripExpense\App\Models\Truck;

class ReportController extends Controller
{
    /**
     * خلاصه کلی گزارش (KPI Cards)
     */
    public function summary(Request $request): JsonResponse
    {
        $fromDate = $request->from_date ?? Carbon::now()->startOfMonth()->toDateString();
        $toDate = $request->to_date ?? Carbon::now()->toDateString();

        $query = TripExpense::query()
            ->whereBetween('expense_date', [$fromDate, $toDate]);

        // فیلتر بر اساس راننده
        if ($request->filled('driver_id')) {
            $query->whereHas('trip', fn ($q) => $q->where('driver_id', $request->driver_id));
        }

        // فیلتر بر اساس کامیون
        if ($request->filled('truck_id')) {
            $query->whereHas('trip', fn ($q) => $q->where('truck_id', $request->truck_id));
        }

        // فیلتر بر اساس دسته‌بندی
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        $totalExpenses = (clone $query)->sum('amount');
        $totalApproved = (clone $query)->where('status', 'approved')->sum('amount');
        $totalPending = (clone $query)->where('status', 'pending')->sum('amount');
        $totalRejected = (clone $query)->where('status', 'rejected')->sum('amount');
        $exceededCount = (clone $query)->where('exceeded_limit', true)->count();
        $totalCount = (clone $query)->count();
        $totalTrips = Trip::whereBetween('start_date', [$fromDate, $toDate])
            ->when($request->filled('driver_id'), fn ($q) => $q->where('driver_id', $request->driver_id))
            ->when($request->filled('truck_id'), fn ($q) => $q->where('truck_id', $request->truck_id))
            ->count();

        $avgPerTrip = $totalTrips > 0 ? $totalExpenses / $totalTrips : 0;

        return response()->json([
            'data' => [
                'total_expenses' => (float) $totalExpenses,
                'total_approved' => (float) $totalApproved,
                'total_pending' => (float) $totalPending,
                'total_rejected' => (float) $totalRejected,
                'exceeded_count' => $exceededCount,
                'total_count' => $totalCount,
                'total_trips' => $totalTrips,
                'avg_per_trip' => round($avgPerTrip, 2),
                'from_date' => $fromDate,
                'to_date' => $toDate,
            ],
        ]);
    }

    /**
     * گزارش بر اساس راننده
     */
    public function byDriver(Request $request): JsonResponse
    {
        $fromDate = $request->from_date ?? Carbon::now()->startOfMonth()->toDateString();
        $toDate = $request->to_date ?? Carbon::now()->toDateString();

        $results = TripExpense::query()
            ->select([
                'trips.driver_id',
                DB::raw('SUM(trip_expenses.amount) as total_amount'),
                DB::raw('COUNT(trip_expenses.id) as total_count'),
                DB::raw('SUM(CASE WHEN trip_expenses.status = "approved" THEN trip_expenses.amount ELSE 0 END) as approved_amount'),
                DB::raw('SUM(CASE WHEN trip_expenses.exceeded_limit = 1 THEN 1 ELSE 0 END) as exceeded_count'),
            ])
            ->join('trips', 'trips.id', '=', 'trip_expenses.trip_id')
            ->join('users', 'users.id', '=', 'trips.driver_id')
            ->whereBetween('trip_expenses.expense_date', [$fromDate, $toDate])
            ->when($request->filled('driver_id'), fn ($q) => $q->where('trips.driver_id', $request->driver_id))
            ->when($request->filled('truck_id'), fn ($q) => $q->where('trips.truck_id', $request->truck_id))
            ->when($request->filled('category_id'), fn ($q) => $q->where('trip_expenses.category_id', $request->category_id))
            ->groupBy('trips.driver_id')
            ->orderByDesc('total_amount')
            ->get()
            ->map(function ($row) {
                $driver = \Modules\Auth\App\Models\User::find($row->driver_id);
                return [
                    'driver_id' => $row->driver_id,
                    'driver_name' => $driver?->name ?? 'نامشخص',
                    'personnel_code' => $driver?->personnel_code ?? '—',
                    'total_amount' => (float) $row->total_amount,
                    'total_count' => (int) $row->total_count,
                    'approved_amount' => (float) $row->approved_amount,
                    'exceeded_count' => (int) $row->exceeded_count,
                ];
            });

        return response()->json(['data' => $results]);
    }

    /**
     * گزارش بر اساس دسته‌بندی
     */
    public function byCategory(Request $request): JsonResponse
    {
        $fromDate = $request->from_date ?? Carbon::now()->startOfMonth()->toDateString();
        $toDate = $request->to_date ?? Carbon::now()->toDateString();

        $results = TripExpense::query()
            ->select([
                'trip_expenses.category_id',
                DB::raw('SUM(trip_expenses.amount) as total_amount'),
                DB::raw('COUNT(trip_expenses.id) as total_count'),
                DB::raw('AVG(trip_expenses.amount) as avg_amount'),
            ])
            ->join('trips', 'trips.id', '=', 'trip_expenses.trip_id')
            ->whereBetween('trip_expenses.expense_date', [$fromDate, $toDate])
            ->when($request->filled('driver_id'), fn ($q) => $q->where('trips.driver_id', $request->driver_id))
            ->when($request->filled('truck_id'), fn ($q) => $q->where('trips.truck_id', $request->truck_id))
            ->when($request->filled('category_id'), fn ($q) => $q->where('trip_expenses.category_id', $request->category_id))
            ->groupBy('trip_expenses.category_id')
            ->orderByDesc('total_amount')
            ->get()
            ->map(function ($row) {
                $category = ExpenseCategory::find($row->category_id);
                return [
                    'category_id' => $row->category_id,
                    'category_name' => $category?->name ?? 'نامشخص',
                    'category_code' => $category?->code ?? '—',
                    'total_amount' => (float) $row->total_amount,
                    'total_count' => (int) $row->total_count,
                    'avg_amount' => round((float) $row->avg_amount, 2),
                ];
            });

        return response()->json(['data' => $results]);
    }

    /**
     * گزارش بر اساس کامیون
     */
    public function byTruck(Request $request): JsonResponse
    {
        $fromDate = $request->from_date ?? Carbon::now()->startOfMonth()->toDateString();
        $toDate = $request->to_date ?? Carbon::now()->toDateString();

        $results = TripExpense::query()
            ->select([
                'trips.truck_id',
                DB::raw('SUM(trip_expenses.amount) as total_amount'),
                DB::raw('COUNT(trip_expenses.id) as total_count'),
                DB::raw('COUNT(DISTINCT trips.id) as total_trips'),
            ])
            ->join('trips', 'trips.id', '=', 'trip_expenses.trip_id')
            ->whereBetween('trip_expenses.expense_date', [$fromDate, $toDate])
            ->when($request->filled('driver_id'), fn ($q) => $q->where('trips.driver_id', $request->driver_id))
            ->when($request->filled('truck_id'), fn ($q) => $q->where('trips.truck_id', $request->truck_id))
            ->when($request->filled('category_id'), fn ($q) => $q->where('trip_expenses.category_id', $request->category_id))
            ->groupBy('trips.truck_id')
            ->orderByDesc('total_amount')
            ->get()
            ->map(function ($row) {
                $truck = Truck::find($row->truck_id);
                return [
                    'truck_id' => $row->truck_id,
                    'truck_code' => $truck?->code ?? '—',
                    'plate_number' => $truck?->plate_number ?? '—',
                    'brand' => $truck?->brand,
                    'total_amount' => (float) $row->total_amount,
                    'total_count' => (int) $row->total_count,
                    'total_trips' => (int) $row->total_trips,
                ];
            });

        return response()->json(['data' => $results]);
    }

    /**
     * گزارش بر اساس مسیر
     */
    public function byRoute(Request $request): JsonResponse
    {
        $fromDate = $request->from_date ?? Carbon::now()->startOfMonth()->toDateString();
        $toDate = $request->to_date ?? Carbon::now()->toDateString();

        $results = TripExpense::query()
            ->select([
                'trips.origin',
                'trips.destination',
                DB::raw('CONCAT(trips.origin, " → ", trips.destination) as route_name'),
                DB::raw('SUM(trip_expenses.amount) as total_amount'),
                DB::raw('COUNT(trip_expenses.id) as total_count'),
                DB::raw('COUNT(DISTINCT trips.id) as total_trips'),
            ])
            ->join('trips', 'trips.id', '=', 'trip_expenses.trip_id')
            ->whereBetween('trip_expenses.expense_date', [$fromDate, $toDate])
            ->when($request->filled('driver_id'), fn ($q) => $q->where('trips.driver_id', $request->driver_id))
            ->when($request->filled('truck_id'), fn ($q) => $q->where('trips.truck_id', $request->truck_id))
            ->when($request->filled('category_id'), fn ($q) => $q->where('trip_expenses.category_id', $request->category_id))
            ->groupBy('trips.origin', 'trips.destination')
            ->orderByDesc('total_amount')
            ->get()
            ->map(function ($row) {
                return [
                    'route_name' => $row->route_name,
                    'origin' => $row->origin,
                    'destination' => $row->destination,
                    'total_amount' => (float) $row->total_amount,
                    'total_count' => (int) $row->total_count,
                    'total_trips' => (int) $row->total_trips,
                ];
            });

        return response()->json(['data' => $results]);
    }

    /**
     * گزارش ماهانه (روند زمانی)
     */
    public function byMonth(Request $request): JsonResponse
    {
        $fromDate = $request->from_date ?? Carbon::now()->subMonths(5)->startOfMonth()->toDateString();
        $toDate = $request->to_date ?? Carbon::now()->toDateString();

        $results = TripExpense::query()
            ->select([
                DB::raw('DATE_FORMAT(trip_expenses.expense_date, "%Y-%m") as month'),
                DB::raw('SUM(trip_expenses.amount) as total_amount'),
                DB::raw('COUNT(trip_expenses.id) as total_count'),
                DB::raw('SUM(CASE WHEN trip_expenses.status = "approved" THEN trip_expenses.amount ELSE 0 END) as approved_amount'),
                DB::raw('SUM(CASE WHEN trip_expenses.exceeded_limit = 1 THEN 1 ELSE 0 END) as exceeded_count'),
            ])
            ->join('trips', 'trips.id', '=', 'trip_expenses.trip_id')
            ->whereBetween('trip_expenses.expense_date', [$fromDate, $toDate])
            ->when($request->filled('driver_id'), fn ($q) => $q->where('trips.driver_id', $request->driver_id))
            ->when($request->filled('truck_id'), fn ($q) => $q->where('trips.truck_id', $request->truck_id))
            ->when($request->filled('category_id'), fn ($q) => $q->where('trip_expenses.category_id', $request->category_id))
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->map(function ($row) {
                return [
                    'month' => $row->month,
                    'total_amount' => (float) $row->total_amount,
                    'total_count' => (int) $row->total_count,
                    'approved_amount' => (float) $row->approved_amount,
                    'exceeded_count' => (int) $row->exceeded_count,
                ];
            });

        return response()->json(['data' => $results]);
    }

    /**
     * گزارش وضعیت تایید
     */
    public function byStatus(Request $request): JsonResponse
    {
        $fromDate = $request->from_date ?? Carbon::now()->startOfMonth()->toDateString();
        $toDate = $request->to_date ?? Carbon::now()->toDateString();

        $results = TripExpense::query()
            ->select([
                'trip_expenses.status',
                DB::raw('SUM(trip_expenses.amount) as total_amount'),
                DB::raw('COUNT(trip_expenses.id) as total_count'),
            ])
            ->join('trips', 'trips.id', '=', 'trip_expenses.trip_id')
            ->whereBetween('trip_expenses.expense_date', [$fromDate, $toDate])
            ->when($request->filled('driver_id'), fn ($q) => $q->where('trips.driver_id', $request->driver_id))
            ->when($request->filled('truck_id'), fn ($q) => $q->where('trips.truck_id', $request->truck_id))
            ->when($request->filled('category_id'), fn ($q) => $q->where('trip_expenses.category_id', $request->category_id))
            ->groupBy('trip_expenses.status')
            ->get()
            ->map(function ($row) {
                return [
                    'status' => $row->status,
                    'total_amount' => (float) $row->total_amount,
                    'total_count' => (int) $row->total_count,
                ];
            });

        return response()->json(['data' => $results]);
    }


    // Modules/TripExpense/App/Http/Controllers/ReportController.php

    /**
     * ریز جزئیات هزینه‌ها (با تمام اطلاعات)
     */
    public function expenseDetails(Request $request): JsonResponse
    {
        $fromDate = $request->from_date ?? Carbon::now()->startOfMonth()->toDateString();
        $toDate = $request->to_date ?? Carbon::now()->toDateString();

        $query = TripExpense::query()
            ->with([
                'trip:id,driver_id,truck_id,origin,destination,start_date,end_date,status',
                'trip.driver:id,name,personnel_code,mobile',
                'trip.truck:id,code,plate_number,brand,model',
                'category:id,name,code',
                'approver:id,name',
            ])
            ->whereBetween('trip_expenses.expense_date', [$fromDate, $toDate]);

        // فیلتر بر اساس راننده
        if ($request->filled('driver_id')) {
            $query->whereHas('trip', fn ($q) => $q->where('driver_id', $request->driver_id));
        }

        // فیلتر بر اساس کامیون
        if ($request->filled('truck_id')) {
            $query->whereHas('trip', fn ($q) => $q->where('truck_id', $request->truck_id));
        }

        // فیلتر بر اساس دسته‌بندی
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // فیلتر بر اساس وضعیت
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // فیلتر تجاوز از سقف
        if ($request->filled('exceeded_limit')) {
            $query->where('exceeded_limit', $request->boolean('exceeded_limit'));
        }

        // جستجو در توضیحات
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhere('warning_message', 'like', "%{$search}%")
                    ->orWhere('rejection_reason', 'like', "%{$search}%");
            });
        }

        $expenses = $query->orderByDesc('trip_expenses.expense_date')
            ->orderByDesc('trip_expenses.id')
            ->paginate($request->get('per_page', 25));

        return response()->json([
            'data' => $expenses->map(function ($expense) {
                return [
                    'id' => $expense->id,
                    'trip_id' => $expense->trip_id,
                    'category_id' => $expense->category_id,
                    'amount' => (float) $expense->amount,
                    'expense_date' => $expense->expense_date?->format('Y-m-d'),
                    'description' => $expense->description,
                    'status' => $expense->status,
                    'status_label' => $this->getStatusLabels()[$expense->status] ?? $expense->status,
                    'exceeded_limit' => $expense->exceeded_limit,
                    'warning_message' => $expense->warning_message,
                    'rejection_reason' => $expense->rejection_reason,
                    'receipt_url' => $expense->receipt_path ? asset('storage/' . $expense->receipt_path) : null,
                    'approved_at' => $expense->approved_at?->format('Y-m-d H:i:s'),
                    'created_at' => $expense->created_at?->format('Y-m-d H:i:s'),

                    // اطلاعات سفر
                    'trip' => [
                        'id' => $expense->trip->id,
                        'origin' => $expense->trip->origin,
                        'destination' => $expense->trip->destination,
                        'start_date' => $expense->trip->start_date?->format('Y-m-d'),
                        'end_date' => $expense->trip->end_date?->format('Y-m-d'),
                        'status' => $expense->trip->status,
                    ],

                    // اطلاعات راننده
                    'driver' => [
                        'id' => $expense->trip->driver->id,
                        'name' => $expense->trip->driver->name,
                        'personnel_code' => $expense->trip->driver->personnel_code,
                        'mobile' => $expense->trip->driver->mobile,
                    ],

                    // اطلاعات کامیون
                    'truck' => [
                        'id' => $expense->trip->truck->id,
                        'code' => $expense->trip->truck->code,
                        'plate_number' => $expense->trip->truck->plate_number,
                        'brand' => $expense->trip->truck->brand,
                        'model' => $expense->trip->truck->model,
                    ],

                    // اطلاعات دسته‌بندی
                    'category' => [
                        'id' => $expense->category->id,
                        'name' => $expense->category->name,
                        'code' => $expense->category->code,
                    ],

                    // اطلاعات تاییدکننده
                    'approver' => $expense->approver ? [
                        'id' => $expense->approver->id,
                        'name' => $expense->approver->name,
                    ] : null,
                ];
            }),
            'meta' => [
                'current_page' => $expenses->currentPage(),
                'per_page' => $expenses->perPage(),
                'total' => $expenses->total(),
                'last_page' => $expenses->lastPage(),
            ],
        ]);
    }



    private function getStatusLabels(): array
    {
        return [
            'pending' => 'در انتظار تایید',
            'approved' => 'تایید شده',
            'rejected' => 'رد شده',
        ];
    }



}
