<?php

namespace Modules\Acl\App\Services;

use Illuminate\Support\Facades\DB;
use Modules\Acl\App\Models\Group;
use Modules\Auth\App\Models\User;
use Spatie\Permission\PermissionRegistrar;

class GroupSyncService
{
    /**
     * sync کل نقش‌های یک کاربر (مستقیم + از گروه‌ها)
     * بعد از هر تغییر در گروه‌ها یا نقش‌های مستقیم صدا زده شود
     */
    public function syncUserRoles(User $user): void
    {
        DB::transaction(function () use ($user) {
            // ۱. نقش‌های مستقیم فعلی کاربر (source = 'direct')
            $directRoleIds = DB::table('model_has_roles')
                ->where('model_type', get_class($user))
                ->where('model_id', $user->id)
                ->where('source', 'direct')
                ->pluck('role_id')
                ->toArray();

            // ۲. نقش‌های همه گروه‌های کاربر
            $groupRoles = $this->getGroupRolesForUser($user);
            // ساختار: [role_id => 'group:{group_id}', ...]

            // ۳. حذف همه نقش‌های کاربر از model_has_roles
            DB::table('model_has_roles')
                ->where('model_type', get_class($user))
                ->where('model_id', $user->id)
                ->delete();

            // ۴. درج مجدد نقش‌ها
            $inserts = [];

            foreach ($directRoleIds as $roleId) {
                $inserts[] = [
                    'role_id'    => $roleId,
                    'model_type' => get_class($user),
                    'model_id'   => $user->id,
                    'source'     => 'direct',
                ];
            }

            foreach ($groupRoles as $roleId => $source) {
                // اگر نقش مستقیم هم هست، source را direct نگه داریم (اولویت با مستقیم)
                if (in_array($roleId, $directRoleIds)) {
                    continue;
                }
                $inserts[] = [
                    'role_id'    => $roleId,
                    'model_type' => get_class($user),
                    'model_id'   => $user->id,
                    'source'     => $source,
                ];
            }

            if (!empty($inserts)) {
                DB::table('model_has_roles')->insert($inserts);
            }

            // ۵. پاکسازی کش spatie
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        });
    }

    /**
     * دریافت نقش‌های همه گروه‌های کاربر
     *
     * @return array<int, string>  [role_id => 'group:{group_id}']
     */
    private function getGroupRolesForUser(User $user): array
    {
        $rows = DB::table('group_user')
            ->join('groups', 'group_user.group_id', '=', 'groups.id')
            ->join('group_role', 'group_user.group_id', '=', 'group_role.group_id')
            ->where('group_user.user_id', $user->id)
            ->where('groups.is_active', true)
            ->whereNull('groups.deleted_at')
            ->get(['group_role.role_id', 'group_user.group_id']);

        $result = [];
        foreach ($rows as $row) {
            if (!isset($result[$row->role_id])) {
                $result[$row->role_id] = 'group:' . $row->group_id;
            }
        }
        return $result;
    }

    /**
     * sync نقش‌های یک گروه به همه اعضای آن
     * وقتی نقش‌های یک گروه تغییر کرد، این متد صدا زده می‌شود
     */
    public function syncGroupMembers(Group $group): void
    {
        $group->load('users');
        foreach ($group->users as $user) {
            $this->syncUserRoles($user);
        }
    }
}
