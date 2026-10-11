<?php

namespace Modules\TripExpense\App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Auth\App\Models\User;
use Modules\TripExpense\App\Http\Requests\DriverAssignmentRequest;
use Modules\TripExpense\App\Http\Resources\DriverAssignmentResource;
use Modules\TripExpense\App\Models\DriverTruck;
use Modules\TripExpense\App\Models\Truck;

class DriverAssignmentController extends Controller
{
    /**
     * لیست اختصاص‌ها
     */
    public function index(Request $request): JsonResponse
    {
        $query = DriverTruck::with(['driver', 'truck']);

        // فیلتر بر اساس راننده
        if ($request->filled('driver_id')) {
            $query->forDriver($request->driver_id);
        }

        // فیلتر بر اساس کامیون
        if ($request->filled('truck_id')) {
            $query->forTruck($request->truck_id);
        }

        // فیلتر وضعیت
        if ($request->filled('status')) {
            if ($request->status === 'active') {
                $query->active();
            } elseif ($request->status === 'ended') {
                $query->ended();
            }
        }

        // جستجو
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('driver', function ($dq) use ($search) {
                    $dq->where('name', 'like', "%{$search}%")
                        ->orWhere('personnel_code', 'like', "%{$search}%");
                })->orWhereHas('truck', function ($tq) use ($search) {
                    $tq->where('code', 'like', "%{$search}%")
                        ->orWhere('plate_number', 'like', "%{$search}%");
                });
            });
        }

        $assignments = $query->orderByDesc('start_date')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => DriverAssignmentResource::collection($assignments),
            'meta' => [
                'current_page' => $assignments->currentPage(),
                'per_page' => $assignments->perPage(),
                'total' => $assignments->total(),
                'last_page' => $assignments->lastPage(),
            ],
        ]);
    }

    /**
     * ایجاد اختصاص جدید
     */
    public function store(DriverAssignmentRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // بررسی تداخل: آیا راننده در این بازه زمانی روی کامیون دیگری است؟
        $conflict = DriverTruck::where('user_id', $validated['user_id'])
            ->whereNull('end_date')
            ->exists();

        if ($conflict) {
            return response()->json([
                'message' => 'این راننده در حال حاضر روی کامیون دیگری فعال است. ابتدا اختصاص قبلی را پایان دهید.',
            ], 422);
        }

        // بررسی تداخل: آیا این کامیون در حال حاضر راننده دیگری دارد؟
        $truckConflict = DriverTruck::where('truck_id', $validated['truck_id'])
            ->whereNull('end_date')
            ->exists();

        if ($truckConflict) {
            return response()->json([
                'message' => 'این کامیون در حال حاضر راننده فعال دیگری دارد. ابتدا اختصاص قبلی را پایان دهید.',
            ], 422);
        }

        $assignment = DriverTruck::create([
            'user_id' => $validated['user_id'],
            'truck_id' => $validated['truck_id'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'] ?? null,
            'note' => $validated['note'] ?? null,
        ]);

        return response()->json([
            'message' => 'راننده با موفقیت به کامیون اختصاص داده شد',
            'data' => new DriverAssignmentResource($assignment->load(['driver', 'truck'])),
        ], 201);
    }

    /**
     * نمایش جزئیات یک اختصاص
     */
    public function show(DriverTruck $driverAssignment): JsonResponse
    {
        return response()->json([
            'data' => new DriverAssignmentResource($driverAssignment->load(['driver', 'truck'])),
        ]);
    }

    /**
     * ویرایش اختصاص
     */
    public function update(DriverAssignmentRequest $request, DriverTruck $driverAssignment): JsonResponse
    {
        $validated = $request->validated();

        $driverAssignment->update([
            'user_id' => $validated['user_id'],
            'truck_id' => $validated['truck_id'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'] ?? null,
            'note' => $validated['note'] ?? null,
        ]);

        return response()->json([
            'message' => 'اختصاص با موفقیت ویرایش شد',
            'data' => new DriverAssignmentResource($driverAssignment->load(['driver', 'truck'])),
        ]);
    }

    /**
     * حذف اختصاص
     */
    public function destroy(DriverTruck $driverAssignment): JsonResponse
    {
        $driverAssignment->delete();

        return response()->json([
            'message' => 'اختصاص با موفقیت حذف شد',
        ]);
    }

    /**
     * پایان دادن به اختصاص فعال
     */
    public function endAssignment(Request $request, DriverTruck $driverAssignment): JsonResponse
    {
        $request->validate([
            'end_date' => 'required|date|date_format:Y-m-d|after_or_equal:start_date',
        ]);

        if (!$driverAssignment->isActive()) {
            return response()->json([
                'message' => 'این اختصاص قبلاً پایان یافته است',
            ], 422);
        }

        $driverAssignment->update([
            'end_date' => $request->end_date,
        ]);

        return response()->json([
            'message' => 'اختصاص با موفقیت پایان یافت',
            'data' => new DriverAssignmentResource($driverAssignment->load(['driver', 'truck'])),
        ]);
    }

    /**
     * تاریخچه اختصاص‌های یک راننده
     */
    public function driverHistory(Request $request, $driverId): JsonResponse
    {
        $assignments = DriverTruck::with(['truck'])
            ->forDriver($driverId)
            ->orderByDesc('start_date')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => DriverAssignmentResource::collection($assignments),
            'meta' => [
                'current_page' => $assignments->currentPage(),
                'per_page' => $assignments->perPage(),
                'total' => $assignments->total(),
                'last_page' => $assignments->lastPage(),
            ],
        ]);
    }

    /**
     * تاریخچه اختصاص‌های یک کامیون
     */
    public function truckHistory(Request $request, $truckId): JsonResponse
    {
        $assignments = DriverTruck::with(['driver'])
            ->forTruck($truckId)
            ->orderByDesc('start_date')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => DriverAssignmentResource::collection($assignments),
            'meta' => [
                'current_page' => $assignments->currentPage(),
                'per_page' => $assignments->perPage(),
                'total' => $assignments->total(),
                'last_page' => $assignments->lastPage(),
            ],
        ]);
    }

    /**
     * دریافت لیست رانندگان برای Select (فقط رانندگان فعال)
     */
    public function availableDrivers(): JsonResponse
    {
        // رانندگانی که در حال حاضر روی کامیونی نیستند
        $activeDriverIds = DriverTruck::active()->pluck('user_id');

        $drivers = User::role('driver')
            ->whereNotIn('id', $activeDriverIds)
            ->where('is_active', true)
            ->get(['id', 'name', 'personnel_code', 'mobile']);

        return response()->json([
            'data' => $drivers,
        ]);
    }

    /**
     * دریافت لیست کامیون‌های موجود برای اختصاص
     */
    public function availableTrucks(): JsonResponse
    {
        // کامیون‌هایی که در حال حاضر راننده فعال ندارند
        $activeTruckIds = DriverTruck::active()->pluck('truck_id');

        $trucks = Truck::active()
            ->whereNotIn('id', $activeTruckIds)
            ->get(['id', 'code', 'plate_number', 'brand', 'model']);

        return response()->json([
            'data' => $trucks->map(function ($truck) {
                return [
                    'id' => $truck->id,
                    'code' => $truck->code,
                    'plate_number' => $truck->plate_number,
                    'display' => "کد {$truck->code} - {$truck->plate_number}",
                ];
            }),
        ]);
    }
}
