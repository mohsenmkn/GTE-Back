<?php

namespace Modules\Complaint\App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Complaint\App\Http\Resources\CategoryResource;
use Modules\Complaint\App\Models\ComplaintCategory;
use Symfony\Component\HttpFoundation\Response;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = ComplaintCategory::query()
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->with(['children.children'])
            ->get();

        return CategoryResource::collection($categories);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'parent_id' => ['nullable', 'integer', 'exists:complaint_categories,id'],
            'title' => ['required', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $level = 1;

        if (! empty($validated['parent_id'])) {
            $parent = ComplaintCategory::findOrFail($validated['parent_id']);

            if ($parent->level >= 3) {
                return response()->json([
                    'message' => 'زیر این دسته نمی‌توان آیتم دیگری تعریف کرد.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $level = $parent->level + 1;
        }

        $category = ComplaintCategory::create([
            'parent_id' => $validated['parent_id'] ?? null,
            'title' => $validated['title'],
            'level' => $level,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'message' => 'دسته‌بندی با موفقیت ایجاد شد.',
            'data' => new CategoryResource($category),
        ], Response::HTTP_CREATED);
    }

    public function update(Request $request, ComplaintCategory $complaintCategory): JsonResponse
    {
        $validated = $request->validate([
            'parent_id' => ['nullable', 'integer', 'exists:complaint_categories,id'],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if (isset($validated['parent_id'])) {
            if ($validated['parent_id'] == $complaintCategory->id) {
                return response()->json([
                    'message' => 'دسته نمی‌تواند زیرمجموعه خودش باشد.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $parent = ComplaintCategory::findOrFail($validated['parent_id']);
            $validated['level'] = $parent->level + 1;
        }

        $complaintCategory->update($validated);

        return response()->json([
            'message' => 'دسته‌بندی با موفقیت ویرایش شد.',
            'data' => new CategoryResource($complaintCategory->fresh()->load('children')),
        ]);
    }

    public function destroy(ComplaintCategory $complaintCategory): JsonResponse
    {
        if ($complaintCategory->children()->exists()) {
            return response()->json([
                'message' => 'این دسته دارای زیرمجموعه است و قابل حذف نیست.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($complaintCategory->complaints()->withTrashed()->exists()) {
            return response()->json([
                'message' => 'برای این دسته شکایت ثبت شده است و قابل حذف نیست.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $complaintCategory->delete();

        return response()->json([
            'message' => 'دسته‌بندی حذف شد.',
        ]);
    }
}
