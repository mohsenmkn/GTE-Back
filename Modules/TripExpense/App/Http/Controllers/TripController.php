<?php

namespace Modules\TripExpense\App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\TripExpense\App\Http\Requests\TripRequest;
use Modules\TripExpense\App\Http\Resources\TripResource;
use Modules\TripExpense\App\Models\Trip;

class TripController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Trip::with(['driver', 'truck']);

        // اگر راننده است، فقط سفرهای خودش
        if (auth()->user()->hasRole('driver')) {
            $query->where('driver_id', auth()->id());
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('driver_id')) {
            $query->where('driver_id', $request->driver_id);
        }

        if ($request->filled('truck_id')) {
            $query->where('truck_id', $request->truck_id);
        }

        if ($request->filled('from_date')) {
            $query->where('start_date', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->where('start_date', '<=', $request->to_date);
        }

        $trips = $query->orderByDesc('start_date')->paginate($request->get('per_page', 15));

        return response()->json(TripResource::collection($trips));
    }

    public function store(TripRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['created_by'] = auth()->id();
        $data['status'] = 'in_progress'; // سفر جدید همیشه در حال انجام است

        $trip = Trip::create($data);

        return response()->json([
            'message' => 'سفر با موفقیت ایجاد شد',
            'data' => new TripResource($trip->load(['driver', 'truck'])),
        ], 201);
    }

    public function show(Trip $trip): JsonResponse
    {
        // راننده فقط می‌تواند سفرهای خودش را ببیند
        if (auth()->user()->hasRole('driver') && $trip->driver_id !== auth()->id()) {
            abort(403, 'دسترسی غیرمجاز');
        }

        return response()->json(new TripResource($trip->load(['driver', 'truck', 'expenses.category'])));
    }

    public function finish(Trip $trip): JsonResponse
    {
        // راننده فقط می‌تواند سفرهای خودش را تمام کند
        if (auth()->user()->hasRole('driver') && $trip->driver_id !== auth()->id()) {
            abort(403, 'دسترسی غیرمجاز');
        }

        if (!$trip->isInProgress()) {
            return response()->json([
                'message' => 'این سفر در حال انجام نیست',
            ], 422);
        }

        $trip->update([
            'status' => 'completed',
            'end_date' => now()->toDateString(),
        ]);

        return response()->json([
            'message' => 'سفر با موفقیت پایان یافت',
            'data' => new TripResource($trip),
        ]);
    }

    public function myTrips(Request $request): JsonResponse
    {
        $trips = Trip::where('driver_id', auth()->id())
            ->with(['truck'])
            ->orderByDesc('start_date')
            ->paginate($request->get('per_page', 15));

        return response()->json(TripResource::collection($trips));
    }
}
