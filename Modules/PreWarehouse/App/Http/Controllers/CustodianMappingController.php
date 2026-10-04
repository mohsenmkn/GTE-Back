<?php


namespace Modules\PreWarehouse\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\PreWarehouse\App\Models\CustodianMapping;

class CustodianMappingController extends Controller
{
    /**
     * لیست متولیان
     */
    public function index(Request $request)
    {
        $mappings = CustodianMapping::with(['user', 'unit'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => $mappings->map(fn($m) => [
                'id' => $m->id,
                'unit' => [
                    'id' => $m->unit->id,
                    'title' => $m->unit->title,
                ],
                'user' => [
                    'id' => $m->user->id,
                    'name' => $m->user->name,
                    'mobile' => $m->user->mobile,
                ],
                'role_title' => $m->role_title,
                'description' => $m->description,
                'is_active' => $m->is_active,
                'created_at' => $m->created_at?->format('Y-m-d H:i:s'),
            ]),
        ]);
    }

    /**
     * ثبت متولی جدید
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'organizational_unit_id' => 'required|exists:organizational_units,id',
            'user_id' => 'required|exists:users,id',
            'role_title' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
        ], [
            'organizational_unit_id.required' => 'انتخاب واحد الزامی است',
            'user_id.required' => 'انتخاب کاربر الزامی است',
        ]);

        // ✅ بررسی وجود رکورد Soft Deleted
        $softDeletedMapping = CustodianMapping::withTrashed()
            ->where('organizational_unit_id', $validated['organizational_unit_id'])
            ->where('user_id', $validated['user_id'])
            ->whereNotNull('deleted_at')
            ->first();

        if ($softDeletedMapping) {
            // ✅ Restore کردن رکورد حذف‌شده
            $softDeletedMapping->restore();

            // به‌روزرسانی اطلاعات
            $softDeletedMapping->update([
                'role_title' => $validated['role_title'] ?? 'متولی کالا',
                'description' => $validated['description'] ?? null,
                'is_active' => $validated['is_active'] ?? true,
            ]);

            return response()->json([
                'message' => 'متولی قبلی بازیابی و به‌روزرسانی شد',
                'data' => $softDeletedMapping->fresh(['user', 'unit']),
            ], 200);
        }

        // بررسی تکراری نبودن (برای رکوردهای فعال)
        $exists = CustodianMapping::where('organizational_unit_id', $validated['organizational_unit_id'])
            ->where('user_id', $validated['user_id'])
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'این کاربر قبلاً به عنوان متولی این واحد ثبت شده است',
            ], 422);
        }

        $mapping = CustodianMapping::create(array_merge($validated, [
            'role_title' => $validated['role_title'] ?? 'متولی کالا',
            'is_active' => $validated['is_active'] ?? true,
        ]));

        return response()->json([
            'message' => 'متولی با موفقیت ثبت شد',
            'data' => $mapping->load('user', 'unit'),
        ], 201);
    }

    /**
     * ویرایش متولی
     */
    public function update(Request $request, int $id)
    {
        $mapping = CustodianMapping::findOrFail($id);

        $validated = $request->validate([
            'organizational_unit_id' => 'required|exists:organizational_units,id',
            'user_id' => 'required|exists:users,id',
            'role_title' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
        ]);

        $mapping->update($validated);

        return response()->json([
            'message' => 'متولی با موفقیت ویرایش شد',
            'data' => $mapping->fresh(['user', 'unit']),
        ]);
    }

    /**
     * حذف متولی
     */
    public function destroy(int $id)
    {
        $mapping = CustodianMapping::findOrFail($id);
        $mapping->delete();

        return response()->json([
            'message' => 'متولی با موفقیت حذف شد',
        ]);
    }
}
