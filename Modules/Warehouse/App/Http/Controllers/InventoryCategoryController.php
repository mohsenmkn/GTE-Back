<?php



namespace Modules\Warehouse\App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Warehouse\App\Models\InventoryCategory;
use Modules\Warehouse\Transformers\InventoryCategoryResource;

class InventoryCategoryController extends Controller
{
    /**
     * لیست دسته‌بندی‌ها
     */
    public function index(Request $request)
    {
        $query = InventoryCategory::query()
            ->withCount('children')
            ->when($request->company_id, fn($q) => $q->where('company_id', $request->company_id))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->parent_id !== null, fn($q) => $q->where('parent_id', $request->parent_id))
            ->when($request->search, function ($q) use ($request) {
                $q->where(function ($query) use ($request) {
                    $query->where('name', 'like', "%{$request->search}%")
                        ->orWhere('code', 'like', "%{$request->search}%");
                });
            })
            ->orderBy('name');

        return InventoryCategoryResource::collection($query->paginate($request->per_page ?? 20));
    }

    /**
     * دریافت درخت دسته‌بندی‌ها
     */
    public function tree(Request $request)
    {
        $categories = InventoryCategory::with(['children' => function ($q) {
            $q->withCount('children');
        }])
            ->where('company_id', $request->company_id)
            ->whereNull('parent_id')
            ->get();

        return InventoryCategoryResource::collection($categories);
    }

    /**
     * ایجاد دسته‌بندی جدید
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50|unique:inventory_categories',
            'parent_id' => 'nullable|exists:inventory_categories,id',
            'description' => 'nullable|string',
            'status' => 'required|in:active,inactive',
        ]);

        // جلوگیری از ایجاد حلقه
        if ($data['parent_id'] ?? false) {
            $parent = InventoryCategory::find($data['parent_id']);
            if ($parent && $parent->parent_id === null) {
                // مجاز است
            }
        }

        $category = InventoryCategory::create($data);
        return new InventoryCategoryResource($category->load('children'));
    }

    /**
     * نمایش یک دسته‌بندی
     */
    public function show(InventoryCategory $category)
    {
        $category->load(['children', 'parent', 'materials']);
        return new InventoryCategoryResource($category);
    }

    /**
     * ویرایش دسته‌بندی
     */
    public function update(Request $request, InventoryCategory $category)
    {
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'code' => 'nullable|string|max:50|unique:inventory_categories,code,' . $category->id,
            'parent_id' => 'nullable|exists:inventory_categories,id',
            'description' => 'nullable|string',
            'status' => 'sometimes|required|in:active,inactive',
        ]);

        // جلوگیری از قرار دادن خودش به عنوان والد
        if (isset($data['parent_id']) && $data['parent_id'] == $category->id) {
            return response()->json([
                'message' => 'نمی‌توانید یک دسته‌بندی را به عنوان والد خودش انتخاب کنید.'
            ], 422);
        }

        $category->update($data);
        return new InventoryCategoryResource($category->load('children'));
    }

    /**
     * حذف دسته‌بندی
     */
    public function destroy(InventoryCategory $category)
    {
        // بررسی وجود زیرمجموعه
        if ($category->children()->exists()) {
            return response()->json([
                'message' => 'این دسته‌بندی دارای زیرمجموعه است و قابل حذف نیست.'
            ], 422);
        }

        // بررسی وجود کالا
        if ($category->materials()->exists()) {
            return response()->json([
                'message' => 'این دسته‌بندی دارای کالا است و قابل حذف نیست.'
            ], 422);
        }

        $category->delete();
        return response()->json(['message' => 'دسته‌بندی با موفقیت حذف شد.'], 200);
    }
}
