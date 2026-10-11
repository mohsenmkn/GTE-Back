<?php

namespace Modules\TripExpense\App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\TripExpense\App\Http\Requests\TruckRequest;
use Modules\TripExpense\App\Http\Resources\TruckResource;
use Modules\TripExpense\App\Models\Truck;

class TruckController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Truck::query();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('plate_number', 'like', "%{$search}%")
                    ->orWhere('brand', 'like', "%{$search}%");
            });
        }

        $trucks = $query->orderBy('code')->paginate($request->get('per_page', 15));

        return response()->json(TruckResource::collection($trucks));
    }

    public function store(TruckRequest $request): JsonResponse
    {
        $truck = Truck::create($request->validated());

        return response()->json([
            'message' => 'کامیون با موفقیت ایجاد شد',
            'data' => new TruckResource($truck),
        ], 201);
    }

    public function show(Truck $truck): JsonResponse
    {
        return response()->json(new TruckResource($truck));
    }

    public function update(TruckRequest $request, Truck $truck): JsonResponse
    {
        $truck->update($request->validated());

        return response()->json([
            'message' => 'کامیون با موفقیت ویرایش شد',
            'data' => new TruckResource($truck),
        ]);
    }

    public function destroy(Truck $truck): JsonResponse
    {
        $truck->delete();

        return response()->json([
            'message' => 'کامیون با موفقیت حذف شد',
        ]);
    }

    /**
     * دریافت کامیون فعلی راننده لاگین شده
     */
    public function myCurrentTruck(): JsonResponse
    {
        $userId = auth()->id();

        // دریافت رابطه فعال راننده با کامیون (end_date = null)
        $driverTruck = \Illuminate\Support\Facades\DB::table('driver_truck')
            ->where('user_id', $userId)
            ->whereNull('end_date')
            ->first();

        if (!$driverTruck) {
            return response()->json([
                'data' => null,
                'message' => 'شما به هیچ کامیونی اختصاص داده نشده‌اید'
            ]);
        }

        $truck = Truck::find($driverTruck->truck_id);

        if (!$truck) {
            return response()->json([
                'data' => null,
                'message' => 'کامیون یافت نشد'
            ]);
        }

        return response()->json([
            'data' => new TruckResource($truck),
            'message' => 'کامیون فعلی شما'
        ]);
    }


    /**
     * دریافت کامیون فعلی اختصاص داده شده به یک راننده خاص (برای مدیر)
     */
    public function getDriverCurrentTruck($userId): JsonResponse
    {
        $driverTruck = \Illuminate\Support\Facades\DB::table('driver_truck')
            ->where('user_id', $userId)
            ->whereNull('end_date') // فقط اختصاص‌های فعال
            ->first();

        if (!$driverTruck) {
            return response()->json([
                'data' => null,
                'message' => 'کامیونی به این راننده اختصاص داده نشده است'
            ]);
        }

        $truck = Truck::find($driverTruck->truck_id);

        return response()->json([
            'data' => $truck ? new TruckResource($truck) : null
        ]);
    }

}
