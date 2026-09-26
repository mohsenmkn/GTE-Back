<?php

namespace Modules\HR\App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Modules\Auth\App\Models\User;
use Modules\HR\App\Models\EmployeePosition;
use Modules\HR\App\Models\EmployeeRelative;
use Modules\HR\App\Models\EmployeeStatuteHistory;
use Modules\HR\App\Models\HRSyncLog;
use Modules\HR\App\Models\OrganizationalPosition;
use Modules\HR\App\Models\OrganizationalUnit;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Str;

class GtarabarSyncService
{
    // ─────────────────────────────────────────────
    //  sync کامل یک کاربر (سمت + خانواده)
    // ─────────────────────────────────────────────
    public function syncUser(User $user, string $triggerSource = 'cli'): ?EmployeePosition
    {
        $startTime = microtime(true);

        try {
            // ۱. sync سمت و واحد
            $position = $this->syncUserPosition($user);

            if (!$position) {
                $this->logSync($user->id, 'all', 'failed', 0, 'No EmployeePosition found', $startTime, $triggerSource);
                return null;
            }

            // ۲. sync خانواده
            $relativesCount = $this->syncEmployeeRelatives($user);

            // ۳. ✅ sync تاریخچه احکام — جدید
            $historyCount = $this->syncStatuteHistory($user);

            $duration = round((microtime(true) - $startTime) * 1000);
            $this->logSync($user->id, 'all', 'success', 1 + $relativesCount, null, $startTime, $triggerSource);

            return $position;
        } catch (\Exception $e) {
            Log::error("HR syncUser({$user->id}) failed: {$e->getMessage()}");
            $this->logSync($user->id, 'all', 'failed', 0, $e->getMessage(), $startTime, $triggerSource);

            EmployeePosition::where('user_id', $user->id)
                ->update(['sync_failed' => true]);

            return null;
        }
    }

    // ─────────────────────────────────────────────
    //  sync سمت و واحد سازمانی
    // ─────────────────────────────────────────────
    public function syncUserPosition(User $user): ?EmployeePosition
    {
        if (empty($user->personnel_code)) return null;

        try {
            $employee = $user->getGtarabarEmployee();
            $statute  = $user->getEmployeeStatute();

            if (!$employee) return null;

            // اگر تغییری نکرده، فقط synced_at را به‌روز کن
            $existing = EmployeePosition::where('user_id', $user->id)->first();

            if ($existing && !$this->hasPositionChanged($existing, $statute)) {

                $unit = null;

                if ($statute?->DepartmentRef) {
                    $unit = $this->syncDepartment($statute->DepartmentRef);
                }

                $organizationalPosition = null;

                if ($unit && $statute?->PostRef) {
                    $organizationalPosition = $this->syncOrganizationalPosition(
                        $unit,
                        $statute
                    );
                }

                $existing->update([
                    'organizational_unit_id'     => $unit?->id,
                    'organizational_position_id' => $organizationalPosition?->id,
                    'synced_at'                  => now(),
                    'sync_failed'                => false,
                ]);

                return $existing;
            }

            // sync واحد سازمانی از DepartmentRef
            $unit = null;
            if ($statute?->DepartmentRef) {
                $unit = $this->syncDepartment($statute->DepartmentRef);
            }

            $organizationalPosition = null;

            if ($unit && $statute?->PostRef) {
                $organizationalPosition = $this->syncOrganizationalPosition(
                    unit: $unit,
                    statute: $statute
                );
            }

            return EmployeePosition::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'personnel_code'                  => $user->personnel_code,
                    'gt_employee_id'                  => $employee->EmployeeID,
                    'gt_statute_id'                   => $statute?->EmployeeStatuteID,

                    'gt_post_ref'                     => $statute?->PostRef,
                    'post_code'                       => $statute?->PostCode,
                    'post_title'                      => $statute?->PostTitle,

                    'gt_job_ref'                      => $statute?->JobRef,
                    'job_code'                        => $statute?->JobCode,
                    'job_title'                       => $statute?->JobTitle,

                    'organizational_unit_id'          => $unit?->id,
                    'organizational_position_id'      => $organizationalPosition?->id,

                    'gt_organizational_structure_ref' => $statute?->OrganizationalStructureRef,
                    'gt_department_ref'               => $statute?->DepartmentRef,

                    'synced_at'                       => now(),
                    'sync_failed'                     => false,
                ]
            );
        } catch (\Exception $e) {
            Log::error("syncUserPosition({$user->id}) failed: {$e->getMessage()}");
            EmployeePosition::where('user_id', $user->id)
                ->update(['sync_failed' => true]);
            return null;
        }
    }

    private function syncOrganizationalPosition(
        OrganizationalUnit $unit,
        object $statute
    ): ?OrganizationalPosition {
        if (!$statute->PostRef) {
            return null;
        }

        try {

            $position = OrganizationalPosition::where(
                'organizational_unit_id',
                $unit->id
            )
                ->where('gt_post_ref', $statute->PostRef)
                ->first();

            // اگر قبلاً توسط ساختار سازمانی ایجاد شده
            if ($position) {

                // ⚠️ ساختار داخلی دست نمی‌خورد
                $position->update([
                    'post_code'   => $statute->PostCode,
                    'post_title'  => $statute->PostTitle,
                    'gt_job_ref'  => $statute->JobRef,
                    'job_code'    => $statute->JobCode,
                    'job_title'   => $statute->JobTitle,
                    'synced_at'   => now(),
                ]);

                return $position;
            }

            // سمت جدید
            return OrganizationalPosition::create([
                'organizational_unit_id' => $unit->id,

                'gt_post_ref'  => $statute->PostRef,
                'post_code'   => $statute->PostCode,
                'post_title'  => $statute->PostTitle,

                'gt_job_ref'   => $statute->JobRef,
                'job_code'    => $statute->JobCode,
                'job_title'   => $statute->JobTitle,

                'is_custom'   => false,
                'is_active'   => true,
                'sort_order'  => 0,

                'synced_at'   => now(),
            ]);

        } catch (\Throwable $e) {

            Log::error(
                "syncOrganizationalPosition failed: {$e->getMessage()}",
                [
                    'unit_id'    => $unit->id,
                    'gt_post_ref' => $statute->PostRef,
                ]
            );

            return null;
        }
    }

    // ─────────────────────────────────────────────
    //  sync اعضای خانواده
    // ─────────────────────────────────────────────
    public function syncEmployeeRelatives(User $user): int
    {
        $position = EmployeePosition::where('user_id', $user->id)->first();

        if (!$position?->gt_employee_id) return 0;

        try {
            $gtRelatives = DB::connection('gtarabar')
                ->table('HCM3.EmployeeRelative')
                ->where('EmployeeRef', $position->gt_employee_id)
                ->whereNull('ExpiryDate')
                ->select(
                    'EmployeeRelativeID',
                    'FirstName',
                    'LastName',
                    'FatherName',
                    'RelationCode',
                    'NationalID',
                    'IDNumber',
                    'BirthDate',
                    'DegreeCode',
                    'EducationStateCode',
                    'PhysicalStateCode',
                    'MaritalStatusCode',
                    'RelativeType',
                    'Job',
                    'Description',
                    'EffectiveDate'
                )
                ->get();

            $syncedCount = 0;
            $gtIds = [];

            foreach ($gtRelatives as $gt) {
                $gtIds[] = $gt->EmployeeRelativeID;

                EmployeeRelative::updateOrCreate(
                    ['gt_relative_id' => $gt->EmployeeRelativeID],
                    [
                        'user_id'               => $user->id,
                        'gt_employee_id'        => $position->gt_employee_id,
                        'first_name'            => $gt->FirstName,
                        'last_name'             => $gt->LastName,
                        'father_name'           => $gt->FatherName,
                        'relation_code'         => $gt->RelationCode,
                        'national_id'           => $gt->NationalID,
                        'id_number'             => $gt->IDNumber,
                        'birth_date'            => $gt->BirthDate,
                        'degree_code'           => $gt->DegreeCode,
                        'education_state_code'  => $gt->EducationStateCode,
                        'physical_state_code'   => $gt->PhysicalStateCode,
                        'marital_status_code'   => $gt->MaritalStatusCode,
                        'relative_type'         => $gt->RelativeType,
                        'job'                   => $gt->Job,
                        'description'           => $gt->Description,
                        'effective_date'        => $gt->EffectiveDate,
                        'synced_at'             => now(),
                    ]
                );

                $syncedCount++;
            }

            // حذف اعضایی که دیگر در گستراب نیستند
//            EmployeeRelative::where('user_id', $user->id)
//                ->whereNotIn('gt_relative_id', $gtIds)
//                ->delete();

            return $syncedCount;
        } catch (\Exception $e) {
            Log::error("syncEmployeeRelatives({$user->id}) failed: {$e->getMessage()}");
            return 0;
        }
    }

    // ─────────────────────────────────────────────
    //  sync واحد سازمانی (بازگشتی)
    // ─────────────────────────────────────────────
    public function syncDepartment(int $departmentId): ?OrganizationalUnit
    {
        $existing = OrganizationalUnit::where('gt_department_id', $departmentId)->first();
        if ($existing) {
            $existing->update(['synced_at' => now()]);
            return $existing;
        }

        try {
            $dept = DB::connection('gtarabar')
                ->table('HCM3.Department')
                ->where('DepartmentID', $departmentId)
                ->select('DepartmentID', 'Code', 'Title', 'Status')
                ->first();

            if (!$dept) return null;

            $parentUnit = null;
            $hierarchy = DB::connection('gtarabar')
                ->table('HCM3.vwOrganizationalStructureParentLevelHierarchy AS v')
                ->join('HCM3.OrganizationalStructure AS o',
                    'o.OrganizationalStructureID', '=', 'v.OrganizationalStructureID')
                ->where('o.DepartmentRef', $departmentId)
                ->whereNull('o.PostRef')
                ->select('v.ParentDepartmentRef')
                ->first();

            if ($hierarchy?->ParentDepartmentRef) {
                $parentUnit = $this->syncDepartment($hierarchy->ParentDepartmentRef);
            }

            $path = $parentUnit ? $parentUnit->path : '/';

            $unit = OrganizationalUnit::create([
                'gt_department_id' => $dept->DepartmentID,
                'code'             => $dept->Code,
                'title'            => $dept->Title,
                'parent_id'        => $parentUnit?->id,
                'level'            => $parentUnit ? $parentUnit->level + 1 : 1,
                'path'             => $path,
                'is_active'        => ($dept->Status ?? 1) == 1,
                'synced_at'        => now(),
            ]);

            $unit->update(['path' => $path . $unit->id . '/']);

            return $unit;
        } catch (\Exception $e) {
            Log::error("syncDepartment({$departmentId}) failed: {$e->getMessage()}");
            return null;
        }
    }

    // ─────────────────────────────────────────────
    //  sync همه کاربران
    // ─────────────────────────────────────────────
    public function syncAll(string $triggerSource = 'scheduler'): array
    {
        $startTime = microtime(true);
        $stats = ['total' => 0, 'success' => 0, 'failed' => 0, 'skipped' => 0];

        User::whereNotNull('personnel_code')
            ->where('personnel_code', '!=', '')
            ->chunkById(100, function ($users) use (&$stats, $triggerSource) {
                foreach ($users as $user) {
                    $stats['total']++;

                    $result = $this->syncUser($user, $triggerSource);

                    if ($result) {
                        $stats['success']++;
                    } else {
                        $stats['failed']++;
                    }
                }
            });

        $duration = round((microtime(true) - $startTime) * 1000);

        Log::info("HR syncAll completed", [
            'stats'       => $stats,
            'duration_ms' => $duration,
            'trigger'     => $triggerSource,
        ]);

        return $stats;
    }

    // ─────────────────────────────────────────────
    //  آیا سمت تغییر کرده؟
    // ─────────────────────────────────────────────
    private function hasPositionChanged(EmployeePosition $existing, ?object $statute): bool
    {
        if (!$statute) return false;

        return $existing->gt_statute_id    !== $statute->EmployeeStatuteID
            || $existing->post_title        !== $statute->PostTitle
            || $existing->job_title         !== $statute->JobTitle
            || $existing->gt_department_ref !== $statute->DepartmentRef;
    }

    // ─────────────────────────────────────────────
    //  ثبت لاگ sync
    // ─────────────────────────────────────────────
    private function logSync(
            ?int $userId,
            string $syncType,
            string $status,
            int $recordsSynced,
            ?string $errorMessage,
            float $startTime,
            string $triggerSource
        ): void
    {
        try {
            HRSyncLog::create([
                'user_id'        => $userId,
                'sync_type'      => $syncType,
                'status'         => $status,
                'records_synced' => $recordsSynced,
                'error_message'  => $errorMessage,
                'duration_ms'    => round((microtime(true) - $startTime) * 1000),
                'trigger_source' => $triggerSource,
            ]);
        } catch (\Exception $e) {
            // لاگ sync نباید باعث خطا شود
        }
    }



// ─────────────────────────────────────────────
//  Import کاربران از گستراب (فقط Status=2)
// ─────────────────────────────────────────────
// ─────────────────────────────────────────────
//  Import کاربران از گستراب (فقط Status=2)
//  همراه با تشخیص پرسنل / پیمانکار
// ─────────────────────────────────────────────
    public function importUsersFromGtarabar(
        int $limit = 0,
        string $roleName = 'پرسنل',
        callable $progressCallback = null
    ): array {
        $stats = [
            'total'          => 0,
            'created'        => 0,
            'updated'        => 0,
            'skipped'        => 0,
            'roles_assigned' => 0,
            'personnel'      => 0,
            'contractors'    => 0,
        ];

        $personnelRole  = Role::firstOrCreate(['name' => $roleName]);
        $contractorRole = Role::firstOrCreate(['name' => $this->getContractorRoleName()]);

        $query = DB::connection('gtarabar')
            ->table('HCM3.Employee AS e')
            ->join('GNR3.Party AS p', 'p.PartyID', '=', 'e.PartyRef')

            // آخرین حکم هر کارمند
            ->leftJoin('HCM3.EmployeeStatute AS es', function ($join) {
                $join->on('es.EmployeeRef', '=', 'e.EmployeeID')
                    ->whereRaw("
                    es.EmployeeStatuteID = (
                        SELECT MAX(es2.EmployeeStatuteID)
                        FROM HCM3.EmployeeStatute es2
                        WHERE es2.EmployeeRef = e.EmployeeID
                    )
                ");
            })

            ->leftJoin('HCM3.Post AS post', 'post.PostID', '=', 'es.PostRef')
            ->leftJoin('HCM3.Job AS job', 'job.JobID', '=', 'es.JobRef')
            ->leftJoin('HCM3.Department AS dept', 'dept.DepartmentID', '=', 'es.DepartmentRef')

            ->where('e.Status', 2)
            ->whereNotNull('e.Code')
            ->where('e.Code', '!=', '')

            ->select(
                'e.EmployeeID',
                'e.Code AS PersonnelCode',
                'e.Status',
                'p.FullName',
                'p.NationalID',
                'p.Mobile',
                'p.Email',
                'dept.Title AS UnitTitle',
                'post.Title AS PostTitle',
                'job.Title AS JobTitle'
            )
            ->orderBy('e.Code');

        if ($limit > 0) {
            $query->limit($limit);
        }

        $employees = $query->get();

        $stats['total'] = $employees->count();

        foreach ($employees as $index => $emp) {
            try {
                // ✅ تشخیص نوع کاربر
                $isContractor = $this->isContractorByTitles(
                    $emp->UnitTitle ?? null,
                    $emp->PostTitle ?? null,
                    $emp->JobTitle ?? null
                );

                $employeeType = $isContractor ? 'contractor' : 'personnel';

                $user = User::where('personnel_code', $emp->PersonnelCode)->first();

                if ($user) {
                    $oldType = $user->employee_type ?? 'personnel';

                    // به‌روزرسانی کاربر موجود
                    $user->update([
                        'name'           => $emp->FullName ?? $user->name,
                        'mobile'         => $emp->Mobile ?? $user->mobile,
                        'email'          => $this->sanitizeEmail($emp->Email, $emp->PersonnelCode, $user->id),
                        'national_code'  => $emp->NationalID ?? $user->national_code,
                        'employee_type'  => $employeeType,
                    ]);

                    // ✅ پیمانکار باید همیشه غیرفعال باشد
                    if ($isContractor && $user->is_active) {
                        $user->update([
                            'is_active' => false,
                        ]);

                        // حذف توکن‌های قبلی برای جلوگیری از دسترسی
                        $user->tokens()->delete();
                    }

                    // ✅ اگر قبلاً پیمانکار بوده و الان پرسنل شده، فعال شود
                    if (!$isContractor && $oldType === 'contractor' && !$user->is_active) {
                        $user->update([
                            'is_active' => true,
                        ]);
                    }

                    $stats['updated']++;
                } else {
                    if ($this->isDuplicateUser($emp)) {
                        $stats['skipped']++;
                        continue;
                    }

                    $user = User::create([
                        'name'           => $emp->FullName ?? 'کاربر ' . $emp->PersonnelCode,
                        'mobile'         => $emp->Mobile,
                        'email'          => $this->sanitizeEmail($emp->Email, $emp->PersonnelCode),
                        'national_code'  => $emp->NationalID,
                        'personnel_code' => $emp->PersonnelCode,
                        'employee_type'  => $employeeType,

                        // ✅ پیمانکار فعال نمی‌شود
                        'is_active'      => !$isContractor,

                        // ✅ برای پیمانکار رمز تصادفی می‌گذاریم
                        'password'       => $isContractor
                            ? Hash::make(Str::random(32))
                            : Hash::make($emp->NationalID ?? $emp->PersonnelCode),
                    ]);

                    $stats['created']++;
                }

                // ✅ مدیریت نقش‌ها
                if ($user) {
                    $targetRole = $isContractor ? $contractorRole->name : $personnelRole->name;
                    $otherRole  = $isContractor ? $personnelRole->name : $contractorRole->name;

                    if ($user->hasRole($otherRole)) {
                        $user->removeRole($otherRole);
                    }

                    if (!$user->hasRole($targetRole)) {
                        $user->assignRole($targetRole);
                        $stats['roles_assigned']++;
                    }
                }

                if ($isContractor) {
                    $stats['contractors']++;
                } else {
                    $stats['personnel']++;
                }

                if ($progressCallback) {
                    $progressCallback($index + 1, $stats['total'], $emp->FullName);
                }
            } catch (\Exception $e) {
                Log::error("Import user {$emp->PersonnelCode} failed: {$e->getMessage()}");
                $stats['skipped']++;
            }
        }

        return $stats;
    }

    /**
     * ✅ تولید email معتبر و یکتا
     * اگر email خالی یا نامعتبر بود، یک email یکتا با personnel_code تولید می‌شود
     */
    private function sanitizeEmail(?string $email, string $personnelCode, ?int $excludeUserId = null): string
    {
        // اگر email معتبر و یکتا بود، همان را برگردان
        if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $exists = User::where('email', $email)
                ->when($excludeUserId, fn($q) => $q->where('id', '!=', $excludeUserId))
                ->exists();

            if (!$exists) {
                return $email;
            }
        }

        // ✅ تولید email یکتا با personnel_code
        return $personnelCode . '@gttmco.local';
    }

    private function isDuplicateUser(object $emp): bool
    {
        if (!empty($emp->Mobile) && User::where('mobile', $emp->Mobile)->exists()) {
            return true;
        }

        if (!empty($emp->NationalID) && User::where('national_code', $emp->NationalID)->exists()) {
            return true;
        }

        return false;
    }

// ─────────────────────────────────────────────
//  Import همه واحدهای سازمانی
// ─────────────────────────────────────────────
    public function importAllUnits(callable $progressCallback = null): array
    {
        $stats = ['total' => 0, 'created' => 0, 'skipped' => 0];

        $departments = DB::connection('gtarabar')
            ->table('HCM3.Department')
            ->select('DepartmentID', 'Code', 'Title', 'Status')
            ->orderBy('DepartmentID')
            ->get();

        $stats['total'] = $departments->count();

        foreach ($departments as $index => $dept) {
            try {
                $existing = OrganizationalUnit::where('gt_department_id', $dept->DepartmentID)->first();
                if ($existing) {
                    $existing->update(['synced_at' => now()]);
                    $stats['skipped']++;
                    continue;
                }

                $this->syncDepartment($dept->DepartmentID);
                $stats['created']++;

                if ($progressCallback) {
                    $progressCallback($index + 1, $stats['total'], $dept->Title);
                }
            } catch (\Exception $e) {
                Log::error("Import unit {$dept->DepartmentID} failed: {$e->getMessage()}");
                $stats['skipped']++;
            }
        }

        return $stats;
    }

// ─────────────────────────────────────────────
//  sync تاریخچه احکام (سمت‌های گذشته)
// ─────────────────────────────────────────────
    public function syncStatuteHistory(User $user): int
    {
    $position = EmployeePosition::where('user_id', $user->id)->first();

    if (!$position?->gt_employee_id) return 0;

    try {
        // خواندن همه احکام از گستراب (جدیدترین اول)
        $statutes = DB::connection('gtarabar')
            ->table('HCM3.EmployeeStatute AS es')
            ->leftJoin('HCM3.Post AS p', 'p.PostID', '=', 'es.PostRef')
            ->leftJoin('HCM3.Job AS j', 'j.JobID', '=', 'es.JobRef')
            ->where('es.EmployeeRef', $position->gt_employee_id)
            ->select(
                'es.EmployeeStatuteID',
                'es.PostRef',
                'p.Code AS PostCode',
                'p.Title AS PostTitle',
                'es.JobRef',
                'j.Code AS JobCode',
                'j.Title AS JobTitle',
                'es.DepartmentRef',
                'es.OrganizationalStructureRef',
                'es.IssueDate',
                'es.ApplyDate',
                'es.ExpiryDate',
                'es.Number'
            )
            ->orderBy('es.EmployeeStatuteID', 'desc')
            ->get();

        if ($statutes->isEmpty()) return 0;

        $syncedCount = 0;
        $gtIds = [];
        $isFirst = true; // اولین رکورد = حکم فعلی

        foreach ($statutes as $statute) {
            $gtIds[] = $statute->EmployeeStatuteID;

            EmployeeStatuteHistory::updateOrCreate(
                ['gt_statute_id' => $statute->EmployeeStatuteID],
                [
                    'user_id'                       => $user->id,
                    'gt_employee_id'                => $position->gt_employee_id,
                    'post_ref'                      => $statute->PostRef,
                    'post_code'                     => $statute->PostCode,
                    'post_title'                    => $statute->PostTitle,
                    'job_ref'                       => $statute->JobRef,
                    'job_code'                      => $statute->JobCode,
                    'job_title'                     => $statute->JobTitle,
                    'department_ref'                => $statute->DepartmentRef,
                    'organizational_structure_ref'  => $statute->OrganizationalStructureRef,
                    'issue_date'                    => $statute->IssueDate,
                    'apply_date'                    => $statute->ApplyDate,
                    'expiry_date'                   => $statute->ExpiryDate,
                    'statute_number'                => $statute->Number,
                    'is_current'                    => $isFirst,
                    'synced_at'                     => now(),
                ]
            );

            $isFirst = false;
            $syncedCount++;
        }

        // حذف احکامی که دیگر در گستراب نیستند
        EmployeeStatuteHistory::where('user_id', $user->id)
            ->whereNotIn('gt_statute_id', $gtIds)
            ->delete();

        return $syncedCount;
    } catch (\Exception $e) {
        Log::error("syncStatuteHistory({$user->id}) failed: {$e->getMessage()}");
        return 0;
    }
    }

// ─────────────────────────────────────────────
//  منطق تشخیص پیمانکار
// ─────────────────────────────────────────────

    /**
     * کلمات کلیدی برای تشخیص پیمانکار
     */
    protected function getContractorKeywords(): array
    {
        return [
            'پیمانکاری',
        ];
    }

    /**
     * نام نقش پیمانکاران
     */
    protected function getContractorRoleName(): string
    {
        return 'پیمانکار';
    }

    /**
     * نرمال‌سازی متن فارسی برای مقایسه
     * با توجه به کالیشن Persian_100_CI_AI، مقایسه را در PHP انجام می‌دهیم
     * تا تفاوت ی/ي، ک/ك و نیم‌فاصله مشکل‌ساز نشود.
     */
    protected function normalizePersian(?string $text): string
    {
        if ($text === null) {
            return '';
        }

        $text = trim($text);

        $map = [
            'ي' => 'ی',
            'ك' => 'ک',
            'أ' => 'ا',
            'إ' => 'ا',
            'ؤ' => 'و',
            'ة' => 'ه',
            "\u{200C}" => '', // نیم‌فاصله
            "\u{200B}" => '', // zero-width space
        ];

        $text = strtr($text, $map);

        // تبدیل فاصله‌های متعدد به یک فاصله
        $text = preg_replace('/\s+/', ' ', $text);

        return $text;
    }

    /**
     * آیا این سه عنوان (واحد، پست، شغل) مربوط به پیمانکار است؟
     *
     * قانون: هر سه فیلد باید کلمه کلیدی «پیمانکاری» داشته باشند.
     * اگر حتی یکی از آن‌ها فاقد کلمه کلیدی باشد، کاربر پیمانکار نیست.
     */
    public function isContractorByTitles(
        ?string $unitTitle,
        ?string $postTitle,
        ?string $jobTitle
    ): bool {
        $unit = $this->normalizePersian($unitTitle);
        $post = $this->normalizePersian($postTitle);
        $job  = $this->normalizePersian($jobTitle);

        // اگر هر کدام از عناوین خالی بود، نمی‌توان پیمانکار تشخیص داد
        if ($unit === '' || $post === '' || $job === '') {
            return false;
        }

        foreach ($this->getContractorKeywords() as $keyword) {
            $normalizedKeyword = $this->normalizePersian($keyword);

            if (
                str_contains($unit, $normalizedKeyword)
                && str_contains($post, $normalizedKeyword)
                && str_contains($job, $normalizedKeyword)
            ) {
                return true;
            }
        }

        return false;
    }

}
