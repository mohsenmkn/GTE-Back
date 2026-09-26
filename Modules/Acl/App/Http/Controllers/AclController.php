<?php

namespace Modules\Acl\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AclController extends Controller
{


    // دریافت لیست کل نقش‌ها
    public function getAllRoles()
    {
        // معمولاً فقط id و name برای دراپ‌داون کافی است
        $roles = Role::with('permissions')->get();
        return response()->json(['data' => $roles]);
    }

    // دریافت لیست کل پرمیشن‌ها (اختیاری در این مرحله، اگر می‌خواهید پرمیشن مستقیم هم بدهید)
    public function getAllPermissions()
    {
        $permissions = Permission::select('id', 'name','display_name')->get();
        return response()->json(['data' => $permissions]);
    }

    public function index()
    {
        // در فرانت‌اند برای ویرایش به پرمیشن‌های هر نقش نیاز داریم
        //return response()->json(Role::with('permissions')->get());
        $roles = Role::with('permissions')->get();
        return response()->json($roles);
    }

    // ساخت نقش جدید
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|unique:roles,name',
                'permissions' => 'nullable|array',
                'permissions.*' => 'string|exists:permissions,name' // نام پرمیشن‌ها
            ]);

            $role = Role::create(['name' => $validated['name'], 'guard_name' => 'web']);

            // اختصاص پرمیشن‌ها به نقش
            if (!empty($validated['permissions'])) {
                $role->syncPermissions($validated['permissions']);
            }

            return response()->json($role->load('permissions'), 201);
        }
        catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()]);
        }

    }

    public function update(Request $request, $id)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255|unique:roles,name,' . $id,
                'permissions' => 'nullable|array',
                'permissions.*' => 'exists:permissions,name',
            ]);

            // ۲. پیدا کردن نقش مورد نظر
            $role = Role::findOrFail($id);

            // ۳. بروزرسانی نام نقش
            $role->update([
                'name' => $request->name
            ]);

            if ($request->has('permissions')) {
                $role->syncPermissions($request->permissions);
            }

            return response()->json([
                'message' => 'نقش با موفقیت بروزرسانی شد.',
                'role' => $role->load('permissions') // ارسال نقش همراه با پرمیشن‌های آپدیت شده
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()]);
        }

    }

    public function destroy($id)
    {
        try {
            $role = Role::findOrFail($id);

            // اختیاری: جلوگیری از حذف نقش‌های سیستمی حساس (مثل ادمین کل)
            if ($role->name === 'super-admin') {
                return response()->json(['message' => 'امکان حذف نقش مدیر کل وجود ندارد.'], 403);
            }

            $role->delete();

            return response()->json(['message' => 'نقش با موفقیت حذف شد.']);
        }
        catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()]);
        }

    }

}
