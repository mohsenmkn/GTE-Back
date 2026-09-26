<?php

namespace Modules\WBS\App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\WBS\App\Models\Task;
use Modules\WBS\App\Models\WbsItem;
use Modules\WBS\App\Http\Requests\StoreTaskRequest;
use Modules\WBS\Transformers\TaskResource;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class TaskController extends Controller
{
    /**
     * GET /api/v1/wbs/{wbsItem}/tasks
     */
    public function index(Request $request, WbsItem $wbsItem): JsonResponse
    {
        // ✅ دیباگ
        \Log::info('Task index - wbsItem:', ['id' => $wbsItem->id]);

        $tasks = $wbsItem->tasks()
            ->with(['assignedUser', 'assignees'])
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->priority, fn($q) => $q->where('priority', $request->priority))
            ->latest()
            ->paginate(15);

        return TaskResource::collection($tasks)->response();
    }

    /**
     * POST /api/v1/wbs/{wbsItem}/tasks
     */
    public function store(StoreTaskRequest $request, WbsItem $wbsItem): JsonResponse
    {
        // ✅ دیباگ
//        \Log::info('Creating task', [
//            'wbs_item_id_from_url' => $wbsItem,
//            'request_data' => $request->all(),
//            'validated_data' => $request->validated(),
//        ]);

        // ✅ دریافت داده‌های validated
        $data = $request->validated();
        $assignees = $data['assignees'] ?? [];
        unset($data['assignees']);

        // ✅ اضافه کردن wbs_item_id
        $data['wbs_item_id'] = $wbsItem->id;

        \Log::info('Final data for create', ['data' => $data]);

        // ✅ ایجاد تسک
        $task = $wbsItem->tasks()->create($data);

        if (!empty($assignees)) {
            $syncData = [];
            foreach ($assignees as $assignee) {
                $syncData[$assignee['user_id']] = ['role' => $assignee['role']];
            }
            $task->assignees()->sync($syncData);
        }

        $task->load(['assignedUser', 'assignees']);

        return (new TaskResource($task))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * GET /api/v1/wbs/{wbsItem}/tasks/{task}
     */
    public function show(WbsItem $wbsItem, Task $task): JsonResponse
    {
        // ✅ اطمینان از اینکه تسک متعلق به wbsItem هست
        if ($task->wbs_item_id !== $wbsItem->id) {
            return response()->json([
                'message' => 'Task not found in this WBS item'
            ], 404);
        }

        $task->load(['assignedUser', 'assignees', 'wbsItem']);

        return (new TaskResource($task))->response();
    }

    /**
     * PUT /api/v1/wbs/{wbsItem}/tasks/{task}
     */
    public function update(Request $request, WbsItem $wbsItem, Task $task): JsonResponse
    {
        // ✅ اطمینان از اینکه تسک متعلق به wbsItem هست
        if ($task->wbs_item_id !== $wbsItem->id) {
            return response()->json([
                'message' => 'Task not found in this WBS item'
            ], 404);
        }

        $data = $request->validate([
            'title'            => 'sometimes|required|string|max:255',
            'description'      => 'nullable|string',
            'assigned_to'      => 'nullable|exists:users,id',
            'start_date'       => 'nullable|date',
            'due_date'         => 'nullable|date|after_or_equal:start_date',
            'priority'         => 'sometimes|required|in:low,medium,high,critical',
            'status'           => 'sometimes|required|in:pending,in_progress,completed,blocked',
            'progress_percent' => 'nullable|numeric|min:0|max:100',
            'estimated_hours'  => 'nullable|integer|min:0',
            'actual_hours'     => 'nullable|integer|min:0',
            'notes'            => 'nullable|string',
            'assignees'        => 'nullable|array',
            'assignees.*.user_id' => 'required|exists:users,id',
            'assignees.*.role'    => 'required|in:assignee,reviewer,observer',
        ]);

        $assignees = $data['assignees'] ?? null;
        unset($data['assignees']);

        $task->update($data);

        // ✅ به‌روزرسانی درصد پیشرفت WBS Item
        $wbsItem->updateProgressRecursive();

        if ($assignees !== null) {
            $syncData = [];
            foreach ($assignees as $assignee) {
                $syncData[$assignee['user_id']] = ['role' => $assignee['role']];
            }
            $task->assignees()->sync($syncData);
        }

        $task->load(['assignedUser', 'assignees']);

        return (new TaskResource($task))->response();
    }

    /**
     * PATCH /api/v1/wbs/{wbsItem}/tasks/{task}/complete
     */
    public function complete(WbsItem $wbsItem, Task $task): JsonResponse
    {
        if ($task->wbs_item_id !== $wbsItem->id) {
            return response()->json([
                'message' => 'Task not found in this WBS item'
            ], 404);
        }

        $task->markAsCompleted();
        // ✅ به‌روزرسانی درصد پیشرفت WBS Item
        $wbsItem->updateProgressRecursive();

        return (new TaskResource($task))->response();
    }

    /**
     * DELETE /api/v1/wbs/{wbsItem}/tasks/{task}
     */
    public function destroy(WbsItem $wbsItem, Task $task): JsonResponse
    {
        if ($task->wbs_item_id !== $wbsItem->id) {
            return response()->json([
                'message' => 'Task not found in this WBS item'
            ], 404);
        }

        $task->delete();

        return response()->json(['message' => 'Task deleted successfully']);
    }
}
