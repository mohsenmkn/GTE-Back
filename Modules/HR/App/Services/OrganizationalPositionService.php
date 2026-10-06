<?php


namespace Modules\HR\App\Services;

use Illuminate\Support\Facades\DB;
use Modules\HR\App\Models\OrganizationalPosition;
use Modules\HR\App\Models\OrganizationalUnit;
use Modules\Auth\App\Models\User;
use Modules\HR\App\Models\EmployeePosition;
use RuntimeException;

class OrganizationalPositionService
{
    /**
     * لیست تمام سمت‌ها
     */
    public function index(?int $unitId = null)
    {
        return OrganizationalPosition::query()
            ->with([
                'unit:id,title,code',
                'parent:id,post_title',
            ])
            ->withCount('employees')
            ->when(
                $unitId,
                fn($q) => $q->where('organizational_unit_id', $unitId)
            )
            ->orderBy('organizational_unit_id')
            ->orderBy('sort_order')
            ->orderBy('post_title')
            ->get();
    }

    /**
     * ساخت درخت مدیریت ساختار سازمانی
     *
     * Unit
     *   └── Position
     *        ├── Position
     *        └── Position
     */
    public function tree(): array
    {
        $units = OrganizationalUnit::query()
            ->where('is_active', true)
            ->with([
                'positions' => function ($query) {
                    $query
                        ->where('is_active', true)
                        ->with([
                            'employees.user:id,name,mobile',
                        ])
                        ->withCount('employees')
                        ->orderBy('sort_order')
                        ->orderBy('post_title');
                },
            ])
            ->orderBy('level')
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get();

        /*
        |--------------------------------------------------------------------------
        | 1. ساخت Node برای همه واحدها
        |--------------------------------------------------------------------------
        */

        $unitMap = [];

        foreach ($units as $unit) {

            $unitMap[$unit->id] = [
                'key' => 'unit-' . $unit->id,

                'data' => [
                    'id'       => $unit->id,
                    'type'     => 'unit',
                    'title'    => $unit->title,
                    'code'     => $unit->code,
                    'level'    => $unit->level,
                    'parent_id'=> $unit->parent_id,
                    'is_custom'=> $unit->is_custom,
                    'is_active'=> $unit->is_active,
                    'sort_order' => $unit->sort_order,
                ],

                'children' => [],
            ];
        }


        /*
        |--------------------------------------------------------------------------
        | 2. اضافه کردن سمت‌ها به واحد مربوطه
        |--------------------------------------------------------------------------
        */

        foreach ($units as $unit) {

            if (!isset($unitMap[$unit->id])) {
                continue;
            }

            $positions = $unit->positions;

            $positionMap = [];

            foreach ($positions as $position) {

                $positionMap[$position->id] = [
                    'key' => 'position-' . $position->id,

                    'data' => [
                        'id' => $position->id,
                        'type' => 'position',

                        'post_title' => $position->post_title,
                        'post_code' => $position->post_code,

                        'job_title' => $position->job_title,
                        'job_code' => $position->job_code,

                        'parent_id' => $position->parent_id,

                        'unit_id' => $unit->id,
                        'unit_title' => $unit->title,

                        'employee_count' => $position->employees_count,

                        'employees' => $position->employees
                            ->map(function ($employee) {
                                return [
                                    'id' => $employee->id,
                                    'user_id' => $employee->user_id,

                                    'name' =>
                                        $employee->user?->name
                                        ?? 'بدون نام',

                                    'personnel_code' =>
                                        $employee->personnel_code,
                                ];
                            })
                            ->values()
                            ->toArray(),

                        'is_custom' => $position->is_custom,
                        'is_active' => $position->is_active,
                        'sort_order' => $position->sort_order,
                    ],

                    'children' => [],
                ];
            }


            /*
            |--------------------------------------------------------------------------
            | ساخت درخت سمت‌ها
            |--------------------------------------------------------------------------
            */

            foreach ($positions as $position) {

                $node = &$positionMap[$position->id];

                if (
                    $position->parent_id &&
                    isset($positionMap[$position->parent_id])
                ) {

                    $positionMap[$position->parent_id]['children'][] = &$node;

                } else {

                    $unitMap[$unit->id]['children'][] = &$node;
                }
            }

            unset($node);
        }


        /*
        |--------------------------------------------------------------------------
        | 3. ساخت درخت واحدهای سازمانی
        |--------------------------------------------------------------------------
        */

        $result = [];

        foreach ($units as $unit) {

            $node = &$unitMap[$unit->id];

            /*
            | اگر والد دارد، زیر والد قرار بگیرد
            */

            if (
                $unit->parent_id &&
                isset($unitMap[$unit->parent_id])
            ) {

                $unitMap[$unit->parent_id]['children'][] = &$node;

            } else {

                /*
                | واحد بدون والد = ریشه
                */

                $result[] = &$node;
            }
        }

        unset($node);

        return $result;
    }

    /**
     * ایجاد سمت
     */
// در فایل OrganizationalPositionService.php


    public function create(array $data): OrganizationalPosition
    {
        return DB::transaction(function () use ($data) {
            $unit = OrganizationalUnit::findOrFail($data['organizational_unit_id']);
            $parentId = $data['parent_id'] ?? null;

            if ($parentId) {
                $parent = OrganizationalPosition::findOrFail($parentId);
                $this->validateParent(null, $parent, $unit);
            }

            $sortOrder = $data['sort_order'] ?? $this->nextSortOrder($unit->id, $parentId);

            // ۱. ایجاد سمت
            $position = OrganizationalPosition::create([
                'organizational_unit_id' => $unit->id,
                'parent_id'              => $parentId,
                'gt_post_ref'            => $data['gt_post_ref'] ?? null,
                'post_code'              => $data['post_code'] ?? null,
                'post_title'             => $data['post_title'] ?? null,
                'gt_job_ref'             => $data['gt_job_ref'] ?? null,
                'job_code'               => $data['job_code'] ?? null,
                'job_title'              => $data['job_title'] ?? null,
                'is_custom'              => $data['is_custom'] ?? true,
                'is_active'              => $data['is_active'] ?? true,
                'sort_order'             => $sortOrder,
                'description'            => $data['description'] ?? null,
            ]);

            // ۲. اگر کاربری انتخاب شده بود، رابطه را در employee_positions ثبت کن
            if (!empty($data['user_id'])) {
                $user = User::findOrFail($data['user_id']);

                EmployeePosition::create([
                    'user_id'                  => $user->id,
                    'personnel_code'           => $user->personnel_code,
                    'organizational_unit_id'   => $position->organizational_unit_id,
                    'organizational_position_id'=> $position->id,
                    'post_code'                => $position->post_code,
                    'post_title'               => $position->post_title,
                    'gt_post_ref'              => $position->gt_post_ref,
                    'job_code'                 => $position->job_code,
                    'job_title'                => $position->job_title,
                    'gt_job_ref'               => $position->gt_job_ref,
                ]);
            }

            return $position;
        });
    }

    /**
     * ویرایش اطلاعات سمت
     */
    public function update(
        OrganizationalPosition $position,
        array                  $data
    ): OrganizationalPosition
    {
        return DB::transaction(function () use ($position, $data) {

            // ── به‌روزرسانی parent_id ─────────────────────────
            if (array_key_exists('parent_id', $data)) {
                $parentId = $data['parent_id'];

                if ($parentId) {
                    $parent = OrganizationalPosition::findOrFail($parentId);
                    $unit = OrganizationalUnit::findOrFail($position->organizational_unit_id);

                    $this->validateParent($position, $parent, $unit);
                }

                $position->parent_id = $parentId;
            }

            // ── به‌روزرسانی سایر فیلدها ──────────────────────
            if (array_key_exists('post_title', $data)) {
                $position->post_title = $data['post_title'];
            }

            if (array_key_exists('post_code', $data)) {
                $position->post_code = $data['post_code'];
            }

            if (array_key_exists('job_title', $data)) {
                $position->job_title = $data['job_title'];
            }

            if (array_key_exists('job_code', $data)) {
                $position->job_code = $data['job_code'];
            }

            if (array_key_exists('description', $data)) {
                $position->description = $data['description'];
            }

            if (array_key_exists('sort_order', $data)) {
                $position->sort_order = $data['sort_order'];
            }

            if (array_key_exists('is_active', $data)) {
                $position->is_active = $data['is_active'];
            }

            $position->save();

            // ── مدیریت تخصیص پرسنل (جدید) ───────────────────
            if (array_key_exists('user_id', $data)) {
                $this->syncEmployeePosition($position, $data['user_id']);
            }

            return $position->fresh([
                'unit',
                'parent',
                'employees.user', // برای بازگرداندن اطلاعات پرسنل جدید
            ]);
        });
    }

    /**
     * همگام‌سازی نگاشت پرسنل با سمت
     * - اگر user_id معتبر باشد: رکورد را ایجاد/به‌روزرسانی می‌کند
     * - اگر null باشد: رکورد نگاشت را حذف می‌کند
     */
    private function syncEmployeePosition(OrganizationalPosition $position, ?int $userId): void
    {
        // ۱. حذف نگاشت فعلی سمت (کاربر قبلی از این سمت خارج می‌شود)
        EmployeePosition::where('organizational_position_id', $position->id)->delete();

        // ۲. اگر کاربر جدیدی انتخاب شده
        if ($userId) {
            $user = User::findOrFail($userId);

            // ۳. حذف رکورد قبلی این کاربر از هر سمت دیگری
            // (به دلیل unique constraint روی user_id)
            EmployeePosition::where('user_id', $user->id)->delete();

            // ۴. ایجاد رکورد جدید برای این کاربر در سمت فعلی
            EmployeePosition::create([
                'user_id'                    => $user->id,
                'personnel_code'             => $user->personnel_code,
                'organizational_unit_id'     => $position->organizational_unit_id,
                'organizational_position_id' => $position->id,
                'post_code'                  => $position->post_code,
                'post_title'                 => $position->post_title,
                'gt_post_ref'                => $position->gt_post_ref,
                'job_code'                   => $position->job_code,
                'job_title'                  => $position->job_title,
                'gt_job_ref'                 => $position->gt_job_ref,
            ]);
        }
    }

    /**
     * جابه‌جایی سمت در ساختار
     */
    /**
     * جابه‌جایی سمت در ساختار
     */
    public function move(
        OrganizationalPosition $position,
        ?int $parentId,
        ?int $sortOrder = null,
        ?int $organizationalUnitId = null
    ): OrganizationalPosition {

        return DB::transaction(function () use (
            $position,
            $parentId,
            $sortOrder,
            $organizationalUnitId
        ) {

            /*
            |--------------------------------------------------------------------------
            | تعیین واحد مقصد
            |--------------------------------------------------------------------------
            */

            if ($parentId !== null) {

                $parent = OrganizationalPosition::findOrFail(
                    $parentId
                );

                /*
                 * وقتی سمت زیر یک سمت دیگر قرار می‌گیرد،
                 * واحد مقصد باید واحد والد باشد.
                 */
                $targetUnitId = $parent->organizational_unit_id;

                $targetUnit = OrganizationalUnit::findOrFail(
                    $targetUnitId
                );

                $this->validateParent(
                    $position,
                    $parent,
                    $targetUnit
                );

            } else {

                /*
                 * وقتی سمت مستقیماً زیر واحد قرار می‌گیرد،
                 * Frontend باید واحد مقصد را ارسال کند.
                 */
                if ($organizationalUnitId === null) {
                    throw new RuntimeException(
                        'واحد سازمانی مقصد مشخص نشده است.'
                    );
                }

                $targetUnit = OrganizationalUnit::findOrFail(
                    $organizationalUnitId
                );

                $targetUnitId = $targetUnit->id;
            }


            /*
            |--------------------------------------------------------------------------
            | وضعیت قبلی
            |--------------------------------------------------------------------------
            */

            $oldUnitId = $position->organizational_unit_id;
            $oldParentId = $position->parent_id;


            /*
            |--------------------------------------------------------------------------
            | تعیین sort_order
            |--------------------------------------------------------------------------
            */

            if ($sortOrder === null) {

                $sortOrder = $this->nextSortOrder(
                    $targetUnitId,
                    $parentId,
                    $position->id
                );
            }


            /*
            |--------------------------------------------------------------------------
            | انتقال
            |--------------------------------------------------------------------------
            */

            $position->organizational_unit_id = $targetUnitId;
            $position->parent_id = $parentId;
            $position->sort_order = $sortOrder;

            $position->save();


            /*
            |--------------------------------------------------------------------------
            | مرتب‌سازی مجدد والد قبلی
            |--------------------------------------------------------------------------
            */

            $this->normalizeSortOrders(
                $oldUnitId,
                $oldParentId
            );


            /*
            |--------------------------------------------------------------------------
            | مرتب‌سازی مجدد والد جدید
            |--------------------------------------------------------------------------
            */

            $this->normalizeSortOrders(
                $targetUnitId,
                $parentId
            );


            /*
            |--------------------------------------------------------------------------
            | خروجی
            |--------------------------------------------------------------------------
            */

            return $position->fresh([
                'unit',
                'parent',
            ]);
        });
    }

    /**
     * حذف سمت
     */
    public function delete(
        OrganizationalPosition $position
    ): void
    {

        if ($position->employees()->exists()) {
            throw new RuntimeException(
                'این سمت دارای پرسنل است و قابل حذف نیست.'
            );
        }

        if ($position->children()->exists()) {
            throw new RuntimeException(
                'این سمت دارای زیرمجموعه است و قابل حذف نیست.'
            );
        }

        $position->delete();
    }

    /**
     * بررسی صحت والد
     */
    private function validateParent(
        ?OrganizationalPosition $position,
        OrganizationalPosition  $parent,
        OrganizationalUnit      $unit
    ): void
    {

        // خودش والد خودش نباشد
        if ($position && $position->id === $parent->id) {
            throw new RuntimeException(
                'یک سمت نمی‌تواند والد خودش باشد.'
            );
        }

        // والد باید در همان واحد باشد
        if (
            $parent->organizational_unit_id !==
            $unit->id
        ) {
            throw new RuntimeException(
                'سمت والد باید متعلق به همان واحد سازمانی باشد.'
            );
        }

        // جلوگیری از ایجاد حلقه
        if ($position) {

            $current = $parent;

            while ($current) {

                if ($current->id === $position->id) {
                    throw new RuntimeException(
                        'امکان ایجاد حلقه در ساختار سازمانی وجود ندارد.'
                    );
                }

                $current = $current->parent;
            }
        }
    }

    /**
     * شماره ترتیب بعدی
     */
    private function nextSortOrder(
        int  $unitId,
        ?int $parentId,
        ?int $exceptId = null
    ): int
    {

        $query = OrganizationalPosition::query()
            ->where('organizational_unit_id', $unitId)
            ->where('parent_id', $parentId);

        if ($exceptId) {
            $query->where('id', '!=', $exceptId);
        }

        return ((int)$query->max('sort_order')) + 1;
    }


    /**
     * مرتب‌سازی مجدد سمت‌های هم‌سطح
     */
    private function normalizeSortOrders(
        int $unitId,
        ?int $parentId
    ): void {

        $positions = OrganizationalPosition::query()
            ->where('organizational_unit_id', $unitId)
            ->where('parent_id', $parentId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        foreach ($positions as $index => $position) {

            if ($position->sort_order !== $index) {

                $position->update([
                    'sort_order' => $index,
                ]);
            }
        }
    }



}
