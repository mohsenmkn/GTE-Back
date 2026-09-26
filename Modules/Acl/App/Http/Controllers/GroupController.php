<?php

namespace Modules\Acl\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Acl\App\Models\Group;
use Modules\Acl\App\Services\GroupSyncService;

class GroupController extends Controller
{
    public function __construct(
        private GroupSyncService $syncService
    ) {}

    /**
     * لیست گروه‌ها با آمار
     */
    public function index(Request $request): JsonResponse
    {
        $groups = Group::withCount(['roles', 'users'])
            ->with('roles:id,name')
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get()
            ->map(fn (Group $g) => [
                'id'                => $g->id,
                'name'              => $g->name,
                'title'             => $g->title,
                'description'       => $g->description,
                'color'             => $g->color,
                'icon'              => $g->icon,
                'is_active'         => $g->is_active,
                'roles_count'       => $g->roles_count,
                'users_count'       => $g->users_count,
                'permissions_count' => $g->permissions_count,
                'roles'             => $g->roles->map(fn ($r) => [
                    'id'   => $r->id,
                    'name' => $r->name,
                ])->values(),
            ]);

        return response()->json(['data' => $groups]);
    }

    /**
     * لیست ساده گروه‌ها (برای dropdown)
     */
    public function all(): JsonResponse
    {
        $groups = Group::active()
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get(['id', 'name', 'title', 'color', 'icon']);

        return response()->json(['data' => $groups]);
    }

    /**
     * نمایش یک گروه با جزئیات
     */
    public function show(Group $group): JsonResponse
    {
        $group->load(['roles:id,name', 'users:id,name,mobile,personnel_code']);

        return response()->json([
            'data' => [
                'id'                => $group->id,
                'name'              => $group->name,
                'title'             => $group->title,
                'description'       => $group->description,
                'color'             => $group->color,
                'icon'              => $group->icon,
                'is_active'         => $group->is_active,
                'roles'             => $group->roles->map(fn ($r) => [
                    'id'   => $r->id,
                    'name' => $r->name,
                ])->values(),
                'users'             => $group->users->map(fn ($u) => [
                    'id'             => $u->id,
                    'name'           => $u->name,
                    'mobile'         => $u->mobile,
                    'personnel_code' => $u->personnel_code,
                ])->values(),
            ],
        ]);
    }

    /**
     * ایجاد گروه جدید
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:50', 'unique:groups,name'],
            'title'       => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'color'       => ['nullable', 'string', 'max:7'],
            'icon'        => ['nullable', 'string', 'max:50'],
            'sort_order'  => ['nullable', 'integer', 'min:0'],
            'role_ids'    => ['nullable', 'array'],
            'role_ids.*'  => ['integer', 'exists:roles,id'],
        ]);

        $group = Group::create($validated);

        if ($request->has('role_ids')) {
            $group->roles()->sync($validated['role_ids'] ?? []);
        }

        return response()->json([
            'message' => 'گروه با موفقیت ایجاد شد',
            'data'    => $group->load('roles:id,name'),
        ], 201);
    }

    /**
     * ویرایش گروه
     */
    public function update(Request $request, Group $group): JsonResponse
    {
        $validated = $request->validate([
            'name'        => ['sometimes', 'required', 'string', 'max:50',
                Rule::unique('groups')->ignore($group->id)],
            'title'       => ['sometimes', 'required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'color'       => ['nullable', 'string', 'max:7'],
            'icon'        => ['nullable', 'string', 'max:50'],
            'is_active'   => ['nullable', 'boolean'],
            'sort_order'  => ['nullable', 'integer', 'min:0'],
            'role_ids'    => ['nullable', 'array'],
            'role_ids.*'  => ['integer', 'exists:roles,id'],
        ]);

        $group->update($validated);

        $rolesChanged = false;
        if ($request->has('role_ids')) {
            $group->roles()->sync($validated['role_ids'] ?? []);
            $rolesChanged = true;
        }

        // ⭐ اگر نقش‌های گروه تغییر کرد، همه اعضا باید sync شوند
        if ($rolesChanged) {
            $this->syncService->syncGroupMembers($group);
        }

        return response()->json([
            'message' => 'گروه با موفقیت ویرایش شد',
            'data'    => $group->fresh()->load('roles:id,name'),
        ]);
    }

    /**
     * حذف گروه
     */
    public function destroy(Group $group): JsonResponse
    {
        // قبل از حذف، نقش‌های همه اعضا را sync کن تا نقش‌های گروه حذف شوند
        $this->syncService->syncGroupMembers($group);
        $group->delete();

        return response()->json(['message' => 'گروه با موفقیت حذف شد']);
    }

    /**
     * اختصاص کاربران به گروه (sync)
     */
    public function assignUsers(Request $request, Group $group): JsonResponse
    {
        $validated = $request->validate([
            'user_ids'   => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', 'exists:users,id'],
            'mode'       => ['nullable', 'in:sync,append'], // sync = جایگزین، append = اضافه
        ]);

        $mode = $validated['mode'] ?? 'sync';
        $syncData = collect($validated['user_ids'])->mapWithKeys(fn ($id) => [
            $id => [
                'assigned_by' => auth()->id(),
                'assigned_at' => now(),
            ],
        ]);

        if ($mode === 'sync') {
            $group->users()->sync($syncData);
        } else {
            $group->users()->syncWithoutDetaching($syncData);
        }

        // sync نقش‌های همه اعضای گروه
        $this->syncService->syncGroupMembers($group);

        return response()->json([
            'message' => 'کاربران با موفقیت به گروه اختصاص شدند',
            'count'   => count($validated['user_ids']),
        ]);
    }

    /**
     * حذف کاربران از گروه
     */
    public function removeUsers(Request $request, Group $group): JsonResponse
    {
        $validated = $request->validate([
            'user_ids'   => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $group->users()->detach($validated['user_ids']);

        // sync نقش‌های کاربران حذف‌شده (نقش‌های گروه از آنها برداشته شود)
        foreach ($validated['user_ids'] as $userId) {
            $user = \Modules\Auth\App\Models\User::find($userId);
            if ($user) {
                $this->syncService->syncUserRoles($user);
            }
        }

        return response()->json([
            'message' => 'کاربران از گروه حذف شدند',
        ]);
    }

    /**
     * دریافت اعضای گروه
     */
    public function users(Group $group): JsonResponse
    {
        $users = $group->users()
            ->get(['users.id', 'users.name', 'users.mobile', 'users.personnel_code', 'users.is_active'])
            ->map(fn ($u) => [
                'id'             => $u->id,
                'name'           => $u->name,
                'mobile'         => $u->mobile,
                'personnel_code' => $u->personnel_code,
                'is_active'      => (bool) $u->is_active,
                'assigned_at'    => $u->pivot->assigned_at,
                'assigned_by'    => $u->pivot->assigned_by,
            ]);

        return response()->json(['data' => $users]);
    }
}
