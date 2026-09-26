<?php

namespace Modules\HR\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\HR\App\Models\OrganizationalUnit;

class OrgStructureController extends Controller
{
    /**
     * GET /api/v1/hr/org-structure
     * لیست تخت واحدها برای مدیریت
     */
    public function index(): JsonResponse
    {
        $units = OrganizationalUnit::select(
            'id', 'title', 'code', 'level', 'parent_id', 'path',
            'is_active', 'is_custom', 'sort_order', 'description'   // ✅ کامل
        )
            ->orderBy('level')
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get();

        return response()->json(['units' => $units]);
    }

    /**
     * PUT /api/v1/hr/org-structure/{unit}
     * تغییر والد یک واحد
     */
    public function updateParent(Request $request, int $unit): JsonResponse
    {
        $unitModel = OrganizationalUnit::findOrFail($unit);
        $newParentId = $request->input('parent_id'); // null = ریشه

        // اعتبارسنجی: parent باید وجود داشته باشد
        if ($newParentId && !OrganizationalUnit::where('id', $newParentId)->exists()) {
            return response()->json(['message' => 'واحد والد یافت نشد.'], 404);
        }

        // اعتبارسنجی: جلوگیری از حلقه
        if ($newParentId && $this->wouldCreateCycle($unitModel, (int) $newParentId)) {
            return response()->json([
                'message' => 'این تغییر باعث ایجاد حلقه در ساختار می‌شود.',
            ], 422);
        }

        // آپدیت parent
        $unitModel->parent_id = $newParentId;
        $unitModel->save();

        // محاسبه مجدد سطح و مسیر
        $this->recalculateUnit($unitModel);

        // به‌روزرسانی همه زیرمجموعه‌ها
        $this->recalculateDescendants($unitModel);

        return response()->json([
            'message' => 'ساختار با موفقیت به‌روز شد.',
            'unit' => $unitModel->fresh(),
        ]);
    }

    /**
     * POST /api/v1/hr/org-structure/reset
     * بازنشانی ساختار (همه واحدها ریشه شوند)
     */
    public function reset(): JsonResponse
    {
        OrganizationalUnit::query()->update([
            'parent_id' => null,
            'level' => 1,
        ]);

        // محاسبه مجدد path
        foreach (OrganizationalUnit::all() as $unit) {
            $unit->update(['path' => '/' . $unit->id . '/']);
        }

        return response()->json(['message' => 'ساختار بازنشانی شد.']);
    }

    /**
     * آیا این تغییر حلقه ایجاد می‌کند؟
     */
    private function wouldCreateCycle(OrganizationalUnit $unit, int $newParentId): bool
    {
        if ($newParentId === $unit->id) return true;

        $current = OrganizationalUnit::find($newParentId);
        $visited = [];

        while ($current) {
            if ($current->id === $unit->id) return true;
            if (in_array($current->id, $visited)) break; // جلوگیری از حلقه بی‌نهایت

            $visited[] = $current->id;
            $current = $current->parent_id
                ? OrganizationalUnit::find($current->parent_id)
                : null;
        }

        return false;
    }

    /**
     * محاسبه مجدد سطح و مسیر یک واحد
     */
    private function recalculateUnit(OrganizationalUnit $unit): void
    {
        if ($unit->parent_id) {
            $parent = OrganizationalUnit::find($unit->parent_id);
            $unit->level = $parent->level + 1;
            $unit->path = $parent->path . $unit->id . '/';
        } else {
            $unit->level = 1;
            $unit->path = '/' . $unit->id . '/';
        }

        $unit->save();
    }

    /**
     * به‌روزرسانی بازگشتی زیرمجموعه‌ها
     */
    private function recalculateDescendants(OrganizationalUnit $unit): void
    {
        $children = OrganizationalUnit::where('parent_id', $unit->id)->get();

        foreach ($children as $child) {
            $child->level = $unit->level + 1;
            $child->path = $unit->path . $child->id . '/';
            $child->save();

            $this->recalculateDescendants($child);
        }
    }



    /**
     * POST /api/v1/hr/org-structure
     * ایجاد واحد جدید (دستی — مثل معاونت‌ها)
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title'       => 'required|string|max:255',
            'code'        => 'nullable|string|max:50',
            'parent_id'   => 'nullable|integer|exists:organizational_units,id',
            'description' => 'nullable|string',
            'sort_order'  => 'nullable|integer',
        ], [
            'title.required' => 'عنوان واحد الزامی است.',
        ]);

        $parentId = $validated['parent_id'] ?? null;
        $level = 1;
        $parentPath = '/';

        if ($parentId) {
            $parent = OrganizationalUnit::find($parentId);
            $level = $parent->level + 1;
            $parentPath = $parent->path;
        }

        $unit = OrganizationalUnit::create([
            'title'       => $validated['title'],
            'code'        => $validated['code'] ?? null,
            'parent_id'   => $parentId,
            'level'       => $level,
            'path'        => '/', // موقتاً
            'is_custom'   => true,
            'is_active'   => true,
            'description' => $validated['description'] ?? null,
            'sort_order'  => $validated['sort_order'] ?? 0,
        ]);

        // اصلاح path با ID واقعی
        $unit->update(['path' => $parentPath . $unit->id . '/']);

        return response()->json([
            'message' => 'واحد با موفقیت ایجاد شد.',
            'unit' => $unit->fresh(),
        ], 201);
    }

    /**
     * PUT /api/v1/hr/org-structure/{unit}
     * ویرایش واحد (نام، کد، والد)
     */
    /**
     * PUT /api/v1/hr/org-structure/{unit}
     * ویرایش واحد — نسخه قطعی
     */
    public function update(Request $request, int $unit): JsonResponse
    {
        $unitModel = OrganizationalUnit::findOrFail($unit);

        $validated = $request->validate([
            'title'       => 'sometimes|required|string|max:255',
            'code'        => 'nullable|string|max:50',
            'parent_id'   => 'nullable|integer|exists:organizational_units,id',
            'description' => 'nullable|string',
            'is_active'   => 'sometimes|boolean',
            'sort_order'  => 'nullable|integer',
        ]);

        Log::info(' OrgStructure update request', [
            'unit_id' => $unit,
            'payload' => $validated,
        ]);

        // ۱) فیلدهای پایه — انتساب مستقیم (بدون fill)
        if (isset($validated['title']))       $unitModel->title = $validated['title'];
        if (array_key_exists('code', $validated))        $unitModel->code = $validated['code'];
        if (array_key_exists('description', $validated)) $unitModel->description = $validated['description'];
        if (array_key_exists('is_active', $validated))   $unitModel->is_active = $validated['is_active'];
        if (array_key_exists('sort_order', $validated))  $unitModel->sort_order = $validated['sort_order'];

        // ۲) والد — انتساب مستقیم
        if (array_key_exists('parent_id', $validated)) {
            $newParentId = $validated['parent_id'];

            // جلوگیری از حلقه
            if ($newParentId && ($newParentId === $unitModel->id || $this->wouldCreateCycle($unitModel, (int) $newParentId))) {
                return response()->json(['message' => 'این تغییر باعث ایجاد حلقه در ساختار می‌شود.'], 422);
            }

            $unitModel->parent_id = $newParentId;

            if ($newParentId) {
                $parent = OrganizationalUnit::findOrFail($newParentId);
                $unitModel->level = $parent->level + 1;
                $unitModel->path  = $parent->path . $unitModel->id . '/';
            } else {
                $unitModel->level = 1;
                $unitModel->path  = '/' . $unitModel->id . '/';
            }
        }

        // ۳) ذخیره — یک save ساده و قطعی
        $unitModel->save();

        Log::info('✅ OrgStructure updated', [
            'unit_id'   => $unitModel->id,
            'parent_id' => $unitModel->parent_id,
            'level'     => $unitModel->level,
        ]);

        // ۴) به‌روزرسانی زیرمجموعه‌ها
        $this->recalculateDescendants($unitModel);

        return response()->json([
            'message' => 'واحد با موفقیت به‌روز شد.',
            'unit'    => $unitModel->fresh(),
        ]);
    }

    /**
     * DELETE /api/v1/hr/org-structure/{unit}
     * حذف واحد
     */
    public function destroy(int $unit): JsonResponse
    {
        $unitModel = OrganizationalUnit::findOrFail($unit);

        // چک زیرمجموعه‌ها
        $childrenCount = OrganizationalUnit::where('parent_id', $unitModel->id)->count();
        if ($childrenCount > 0) {
            return response()->json([
                'message' => "این واحد {$childrenCount} زیرمجموعه دارد. ابتدا آن‌ها را منتقل یا حذف کنید.",
            ], 422);
        }

        // چک پرسنل مرتبط
        $positionsCount = \Modules\HR\App\Models\EmployeePosition::where('organizational_unit_id', $unitModel->id)->count();
        if ($positionsCount > 0) {
            return response()->json([
                'message' => "{$positionsCount} کارمند به این واحد مرتبط هستند. ابتدا واحد آن‌ها را تغییر دهید.",
            ], 422);
        }

        $title = $unitModel->title;
        $unitModel->delete();

        return response()->json([
            'message' => "واحد «{$title}» با موفقیت حذف شد.",
        ]);
    }


}
