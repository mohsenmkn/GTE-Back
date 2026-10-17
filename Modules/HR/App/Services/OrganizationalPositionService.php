<?php

namespace Modules\HR\App\Services;

use Illuminate\Http\UploadedFile;
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
     *        └── Unit (زیر سمت) ✅
     *             └── Position
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

        // ── ۱. ساخت Node برای همه واحدها ────────────────

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
                    'parent_position_id' => $unit->parent_position_id, // ✅ اضافه شد
                    'is_custom'=> $unit->is_custom,
                    'is_active'=> $unit->is_active,
                    'sort_order' => $unit->sort_order,
                ],
                'children' => [],
            ];
        }

        // ── ۲. ساخت Node برای همه سمت‌ها ────────────────
        // ✅ $positionMap بیرون از حلقه units تعریف شده

        $positionMap = [];
        foreach ($units as $unit) {
            if (!isset($unitMap[$unit->id])) {
                continue;
            }
            $positions = $unit->positions;
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
                        'unit_id' => $position->organizational_unit_id,
                        'unit_title' => $unit->title,
                        'is_custom' => $position->is_custom,
                        'is_active' => $position->is_active,
                        'sort_order' => $position->sort_order,
                        'employee_count' => $position->employees_count,
                        'employees' => $position->employees
                            ->map(function ($employee) {
                                return [
                                    'id' => $employee->id,
                                    'user_id' => $employee->user_id,
                                    'name' => $employee->user?->name ?? 'بدون نام',
                                    'personnel_code' => $employee->personnel_code,
                                ];
                            })
                            ->values()
                            ->toArray(),
                    ],
                    'children' => [],
                ];
            }
        }

        // ── ۳. ساخت درخت سمت‌ها ─────────────────────────

        foreach ($positionMap as $positionId => &$positionNode) {
            $parentId = $positionNode['data']['parent_id'];
            $unitId = $positionNode['data']['unit_id'];

            if ($parentId && isset($positionMap[$parentId])) {
                // سمت غیر ریشه → زیر سمت والد
                $positionMap[$parentId]['children'][] = &$positionNode;
            } else {
                // سمت ریشه → زیر واحد مربوطه
                if (isset($unitMap[$unitId])) {
                    $unitMap[$unitId]['children'][] = &$positionNode;
                }
            }
        }
        unset($positionNode);

        // ── ۴. ساخت درخت واحدها ─────────────────────────

        $result = [];
        foreach ($units as $unit) {
            $node = &$unitMap[$unit->id];

            if ($unit->parent_id && isset($unitMap[$unit->parent_id])) {
                // واحد زیر واحد دیگر
                $unitMap[$unit->parent_id]['children'][] = &$node;
            } elseif ($unit->parent_position_id && isset($positionMap[$unit->parent_position_id])) {
                // ✅ واحد زیر سمت
                $positionMap[$unit->parent_position_id]['children'][] = &$node;
            } else {
                // واحد ریشه
                $result[] = &$node;
            }
        }
        unset($node);

        return $result;
    }

    /**
     * ایجاد سمت
     */
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

                // ✅ حذف رکوردهای قبلی کاربر (اگر سمت دیگری داشته)
                EmployeePosition::where('user_id', $user->id)->delete();

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
    ): OrganizationalPosition {
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
                'employees.user',
            ]);
        });
    }

    /**
     * همگام‌سازی نگاشت پرسنل با سمت
     */
    private function syncEmployeePosition(OrganizationalPosition $position, ?int $userId): void
    {
        EmployeePosition::where('organizational_position_id', $position->id)->delete();

        if ($userId) {
            $user = User::findOrFail($userId);
            EmployeePosition::where('user_id', $user->id)->delete();

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
            if ($parentId !== null) {
                $parent = OrganizationalPosition::findOrFail($parentId);
                $targetUnitId = $parent->organizational_unit_id;
                $targetUnit = OrganizationalUnit::findOrFail($targetUnitId);
                $this->validateParent($position, $parent, $targetUnit);
            } else {
                if ($organizationalUnitId === null) {
                    throw new RuntimeException('واحد سازمانی مقصد مشخص نشده است.');
                }
                $targetUnit = OrganizationalUnit::findOrFail($organizationalUnitId);
                $targetUnitId = $targetUnit->id;
            }

            $oldUnitId = $position->organizational_unit_id;
            $oldParentId = $position->parent_id;

            if ($sortOrder === null) {
                $sortOrder = $this->nextSortOrder($targetUnitId, $parentId, $position->id);
            }

            $position->organizational_unit_id = $targetUnitId;
            $position->parent_id = $parentId;
            $position->sort_order = $sortOrder;
            $position->save();

            $this->normalizeSortOrders($oldUnitId, $oldParentId);
            $this->normalizeSortOrders($targetUnitId, $parentId);

            return $position->fresh(['unit', 'parent']);
        });
    }

    /**
     * حذف سمت
     */
    public function delete(OrganizationalPosition $position): void
    {
        if ($position->employees()->exists()) {
            throw new RuntimeException('این سمت دارای پرسنل است و قابل حذف نیست.');
        }

        if ($position->children()->exists()) {
            throw new RuntimeException('این سمت دارای زیرمجموعه است و قابل حذف نیست.');
        }

        // ✅ بررسی اینکه آیا واحدی زیر این سمت قرار دارد
        if (OrganizationalUnit::where('parent_position_id', $position->id)->exists()) {
            throw new RuntimeException('این سمت دارای واحدهای زیرمجموعه است و قابل حذف نیست.');
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
    ): void {
        if ($position && $position->id === $parent->id) {
            throw new RuntimeException('یک سمت نمی‌تواند والد خودش باشد.');
        }

        if ($parent->organizational_unit_id !== $unit->id) {
            throw new RuntimeException('سمت والد باید متعلق به همان واحد سازمانی باشد.');
        }

        if ($position) {
            $current = $parent;
            while ($current) {
                if ($current->id === $position->id) {
                    throw new RuntimeException('امکان ایجاد حلقه در ساختار سازمانی وجود ندارد.');
                }
                $current = $current->parent;
            }
        }
    }

    /**
     * شماره ترتیب بعدی
     */
    private function nextSortOrder(int $unitId, ?int $parentId, ?int $exceptId = null): int
    {
        $query = OrganizationalPosition::query()
            ->where('organizational_unit_id', $unitId)
            ->where('parent_id', $parentId);

        if ($exceptId) {
            $query->where('id', '!=', $exceptId);
        }

        return ((int) $query->max('sort_order')) + 1;
    }

    /**
     * مرتب‌سازی مجدد سمت‌های هم‌سطح
     */
    private function normalizeSortOrders(int $unitId, ?int $parentId): void
    {
        $positions = OrganizationalPosition::query()
            ->where('organizational_unit_id', $unitId)
            ->where('parent_id', $parentId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        foreach ($positions as $index => $position) {
            if ($position->sort_order !== $index) {
                $position->update(['sort_order' => $index]);
            }
        }
    }


    /**
     * تخصیص گروهی پرسنل به یک سمت
     *
     * @param OrganizationalPosition $position سمت مقصد
     * @param array $userIds لیست شناسه کاربران
     * @return array نتیجه عملیات
     */
    public function assignEmployees(
        OrganizationalPosition $position,
        array $userIds
    ): array {
        if (!$position->is_active) {
            throw new RuntimeException('سمت غیرفعال است و امکان تخصیص پرسنل وجود ندارد.');
        }

        $result = [
            'assigned' => [],
            'failed' => [],
        ];

        DB::transaction(function () use ($position, $userIds, &$result) {
            foreach ($userIds as $userId) {
                try {
                    $user = User::findOrFail($userId);

                    // بررسی فعال بودن کاربر
                    if (!$user->is_active) {
                        $result['failed'][] = [
                            'user_id' => $userId,
                            'name' => $user->name,
                            'personnel_code' => $user->personnel_code,
                            'reason' => 'کاربر غیرفعال است.',
                        ];
                        continue;
                    }

                    // بررسی اینکه آیا کاربر در حال حاضر در همین سمت است
                    $isAlreadyAssigned = EmployeePosition::where('user_id', $user->id)
                        ->where('organizational_position_id', $position->id)
                        ->exists();

                    if ($isAlreadyAssigned) {
                        $result['failed'][] = [
                            'user_id' => $userId,
                            'name' => $user->name,
                            'personnel_code' => $user->personnel_code,
                            'reason' => 'قبلاً به این سمت تخصیص یافته است.',
                        ];
                        continue;
                    }

                    // حذف نگاشت قبلی کاربر
                    EmployeePosition::where('user_id', $user->id)->delete();

                    // ایجاد نگاشت جدید
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
                        'synced_at'                  => now(),
                        'sync_failed'                => false,
                    ]);

                    $result['assigned'][] = [
                        'user_id' => $userId,
                        'name' => $user->name,
                        'personnel_code' => $user->personnel_code,
                    ];
                } catch (\Throwable $e) {
                    $result['failed'][] = [
                        'user_id' => $userId,
                        'reason' => $e->getMessage(),
                    ];
                }
            }
        });

        return $result;
    }

    /**
     * حذف تخصیص پرسنل از یک سمت
     *
     * @param OrganizationalPosition $position سمت
     * @param array $userIds لیست شناسه کاربران (اگر خالی باشد، همه)
     * @return array نتیجه عملیات
     */
    public function unassignEmployees(
        OrganizationalPosition $position,
        array $userIds = []
    ): array {
        $query = EmployeePosition::where('organizational_position_id', $position->id);

        if (!empty($userIds)) {
            $query->whereIn('user_id', $userIds);
        }

        $deleted = $query->delete();

        return [
            'removed_count' => $deleted,
        ];
    }

    /**
     * لیست پرسنل قابل تخصیص (که هنوز به این سمت تخصیص نیافته‌اند)
     */
    public function availableEmployees(
        OrganizationalPosition $position,
        ?string $search = null
    ) {
        $query = User::query()
            ->where('users.is_active', true)
            ->whereNotNull('users.personnel_code')
            ->where('users.personnel_code', '!=', '')
            ->whereNotIn('users.id', function ($q) use ($position) {
                $q->select('user_id')
                    ->from('employee_positions')
                    ->where('organizational_position_id', $position->id);
            })
            ->leftJoin('employee_positions', 'employee_positions.user_id', '=', 'users.id')
            ->select(
                'users.id',
                'users.name',
                'users.personnel_code',
                'users.mobile',
                'employee_positions.post_title as current_post_title',
                'employee_positions.organizational_unit_id as current_unit_id'
            );

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('users.name', 'LIKE', "%{$search}%")
                    ->orWhere('users.personnel_code', 'LIKE', "%{$search}%")
                    ->orWhere('users.mobile', 'LIKE', "%{$search}%");
            });
        }

        return $query->orderBy('users.personnel_code')
            ->limit(100)
            ->get();
    }

    /**
     * پیش‌نمایش import از فایل اکسل
     */
    public function previewExcelImport(
        OrganizationalPosition $position,
        UploadedFile $file
    ): array {
        if (!$position->is_active) {
            throw new RuntimeException('سمت غیرفعال است.');
        }

        $personnelCodes = $this->parseExcelFile($file);

        if (empty($personnelCodes)) {
            throw new RuntimeException('هیچ کد پرسنلی در فایل یافت نشد.');
        }

        if (count($personnelCodes) > 1000) {
            throw new RuntimeException('حداکثر 1000 رکورد در هر فایل مجاز است.');
        }

        $valid = [];
        $invalid = [];
        $alreadyAssigned = [];

        // حذف کدهای تکراری
        $uniqueCodes = array_unique($personnelCodes);

        foreach ($uniqueCodes as $code) {
            $code = trim((string) $code);

            if (empty($code)) {
                continue;
            }

            $user = User::where('personnel_code', $code)->first();

            if (!$user) {
                $invalid[] = [
                    'personnel_code' => $code,
                    'name' => null,
                    'reason' => 'کاربر با این کد پرسنلی یافت نشد',
                ];
                continue;
            }

            if (!$user->is_active) {
                $invalid[] = [
                    'personnel_code' => $code,
                    'name' => $user->name,
                    'reason' => 'کاربر غیرفعال است',
                ];
                continue;
            }

            // بررسی اینکه آیا کاربر در حال حاضر در همین سمت است
            $isAlreadyInPosition = EmployeePosition::where('user_id', $user->id)
                ->where('organizational_position_id', $position->id)
                ->exists();

            if ($isAlreadyInPosition) {
                $alreadyAssigned[] = [
                    'personnel_code' => $code,
                    'name' => $user->name,
                ];
                continue;
            }

            // بررسی سمت فعلی کاربر
            $currentAssignment = EmployeePosition::with('unit:id,title')
                ->where('user_id', $user->id)
                ->first();

            $valid[] = [
                'user_id' => $user->id,
                'personnel_code' => $code,
                'name' => $user->name,
                'current_position' => $currentAssignment?->post_title,
                'current_unit' => $currentAssignment?->unit?->title,
            ];
        }

        return [
            'valid' => $valid,
            'invalid' => $invalid,
            'already_assigned' => $alreadyAssigned,
            'summary' => [
                'total' => count($uniqueCodes),
                'valid' => count($valid),
                'invalid' => count($invalid),
                'already_assigned' => count($alreadyAssigned),
            ],
        ];
    }

    /**
     * پارس کردن فایل اکسل و استخراج کدهای پرسنلی
     */
    private function parseExcelFile(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if (!in_array($extension, ['xlsx', 'xls', 'csv'])) {
            throw new RuntimeException(
                'فرمت فایل نامعتبر است. فقط فایل‌های Excel (xlsx, xls) و CSV مجاز هستند.'
            );
        }

        $filePath = $file->getRealPath();

        if ($extension === 'csv') {
            return $this->parseCsvFile($filePath);
        }

        return $this->parseExcelSpreadsheet($filePath);
    }

    private function parseCsvFile(string $filePath): array
    {
        $codes = [];
        $handle = fopen($filePath, 'r');

        if (!$handle) {
            throw new RuntimeException('خطا در خواندن فایل CSV');
        }

        $isFirstRow = true;
        while (($row = fgetcsv($handle)) !== false) {
            if ($isFirstRow) {
                $isFirstRow = false;
                if ($this->isHeaderRow($row)) {
                    continue;
                }
            }

            if (!empty($row[0])) {
                $codes[] = trim((string) $row[0]);
            }
        }

        fclose($handle);

        return $codes;
    }

    private function parseExcelSpreadsheet(string $filePath): array
    {
        if (!class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class)) {
            throw new RuntimeException(
                'پکیج PhpSpreadsheet نصب نیست. لطفاً با دستور زیر نصب کنید: ' .
                'composer require phpoffice/phpspreadsheet'
            );
        }

        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filePath);
            $rows = $spreadsheet->getActiveSheet()->toArray();
        } catch (\Throwable $e) {
            throw new RuntimeException('خطا در خواندن فایل اکسل: ' . $e->getMessage());
        }

        $codes = [];
        $isFirstRow = true;

        foreach ($rows as $row) {
            if ($isFirstRow) {
                $isFirstRow = false;
                if ($this->isHeaderRow($row)) {
                    continue;
                }
            }

            if (!empty($row[0])) {
                $codes[] = trim((string) $row[0]);
            }
        }

        return $codes;
    }

    /**
     * تشخیص ردیف عنوان در فایل اکسل
     */
    private function isHeaderRow(array $row): bool
    {
        if (empty($row)) return false;

        $firstCell = strtolower(trim((string) ($row[0] ?? '')));

        if (empty($firstCell)) return false;

        $headerKeywords = [
            'کد پرسنلی', 'کدپرسنلی', 'کد پرسنلي', 'پرسنلی', 'شماره پرسنلی',
            'personnel_code', 'personnel code', 'personnelcode',
            'کد', 'code',
        ];

        foreach ($headerKeywords as $keyword) {
            if (str_contains($firstCell, $keyword)) {
                return true;
            }
        }

        return false;
    }
}
