<?php


// Modules/WBS/Http/Controllers/WbsItemController.php

namespace Modules\WBS\App\Http\Controllers;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\WBS\App\Models\WbsItem;
use Modules\WBS\App\Http\Requests\StoreWbsItemRequest;
use Modules\WBS\Transformers\WbsItemResource;

class WbsItemController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = WbsItem::withCount('tasks')
            ->when($request->project_id, fn($q) => $q->where('project_id', $request->project_id))
            ->when($request->category, fn($q) => $q->where('category', $request->category))
            ->when($request->parent_id, fn($q) => $q->where('parent_id', $request->parent_id))
            ->when($request->root_only, fn($q) => $q->roots());

        if ($request->tree) {
            $items = $query->roots()->with('children.children')->get();
        } else {
            $items = $query->paginate(20);
        }

        return WbsItemResource::collection($items)->response();
    }

    public function store(StoreWbsItemRequest $request): JsonResponse
    {
        $data = $request->validated();

        // محاسبه total_price
        if (isset($data['quantity']) && isset($data['unit_price'])) {
            $data['total_price'] = $data['quantity'] * $data['unit_price'];
        }

        // ✅ اطمینان از اینکه parent_id اگر null هست، به درستی ست شود
        if (!isset($data['parent_id']) || $data['parent_id'] === '' || $data['parent_id'] === 'null') {
            $data['parent_id'] = null;
        }

        $wbsItem = WbsItem::create($data);

        return (new WbsItemResource($wbsItem))
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, $id): JsonResponse
    {
        try {
            $wbsItem = WbsItem::findOrFail($id);

            // ✅ دریافت تمام داده‌ها
            $data = $request->all();

            // ✅ لاگ برای دیباگ
            \Log::info('Updating WBS Item', [
                'id' => $id,
                'data' => $data
            ]);

            // ✅ validation با قوانین به‌روز
            $validated = $request->validate([
                'code'        => 'sometimes|required|string|max:50',
                'name'        => 'sometimes|required|string|max:255',
                'category'    => 'sometimes|required|in:civil,electrical,mechanical',
                'unit'        => 'nullable|string|max:50',
                'quantity'    => 'nullable|numeric|min:0',
                'unit_price'  => 'nullable|numeric|min:0',
                'weight'      => 'nullable|numeric|min:0|max:100',
                'status'      => 'sometimes|required|in:pending,in_progress,completed,on_hold',
                'description' => 'nullable|string',
            ]);

            // ✅ محاسبه total_price
            if (isset($validated['quantity']) && isset($validated['unit_price'])) {
                $validated['total_price'] = $validated['quantity'] * $validated['unit_price'];
            }

            // ✅ به‌روزرسانی با fill
            $wbsItem->fill($validated);
            $wbsItem->save();

            if ($wbsItem->parent_id) {
                $parent = WbsItem::find($wbsItem->parent_id);
                if ($parent) {
                    $parent->updateProgressRecursive();
                }
            }

            // ✅ بارگذاری مجدد با روابط
            $wbsItem->load(['parent', 'children', 'tasks'])
                ->loadCount('tasks');

            return (new WbsItemResource($wbsItem))->response();

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'WBS Item not found'
            ], 404);
        } catch (\Exception $e) {
            \Log::error('Error updating WBS Item', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Error updating WBS item',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id): JsonResponse
{
    try {
        $wbsItem = WbsItem::with(['parent', 'children', 'tasks'])
            ->withCount('tasks')
            ->findOrFail($id);  // ✅ استفاده از findOrFail

        return (new WbsItemResource($wbsItem))->response();

    } catch (ModelNotFoundException $e) {
        return response()->json([
            'message' => 'WBS Item not found',
            'error' => 'The requested WBS item does not exist'
        ], 404);
    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Error loading WBS item',
            'error' => $e->getMessage()
        ], 500);
    }
}

    public function destroy(WbsItem $wbsItem): JsonResponse
    {
        $wbsItem->delete();

        return response()->json(['message' => 'WBS Item deleted successfully']);
    }

    public function tree(Request $request): JsonResponse
    {
        // ✅ باید با withCount بارگذاری بشه
        $items = WbsItem::with(['children' => function($query) {
            $query->withCount('tasks');  // ✅ برای فرزندان هم count بگیر
        }])
            ->withCount('tasks')  // ✅ count تسک‌های خود آیتم
            ->where('project_id', $request->project_id)
            ->roots()
            ->get();

        return WbsItemResource::collection($items)->response();
    }
}
