<?php

namespace Modules\Library\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Library\App\Services\LibraryService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    public function __construct(
        private LibraryService $libraryService
    ) {}

    /**
     * GET /api/library/categories
     */
    public function index(): JsonResponse
    {
        $categories = $this->libraryService->getCategories();
        return response()->json(['data' => $categories]);
    }

    /**
     * POST /api/library/admin/categories
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'color' => 'nullable|string|max:20',
        ]);

        $category = $this->libraryService->createCategory($validated);

        return response()->json([
            'message' => 'دسته‌بندی با موفقیت ایجاد شد.',
            'data' => $category,
        ], 201);
    }

    /**
     * PUT /api/library/admin/categories/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'color' => 'nullable|string|max:20',
        ]);

        $category = $this->libraryService->updateCategory($id, $validated);

        return response()->json([
            'message' => 'دسته‌بندی با موفقیت ویرایش شد.',
            'data' => $category,
        ]);
    }

    /**
     * DELETE /api/library/admin/categories/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $this->libraryService->deleteCategory($id);

        return response()->json(['message' => 'دسته‌بندی با موفقیت حذف شد.']);
    }
}
