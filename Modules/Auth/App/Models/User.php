<?php

namespace Modules\Auth\App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\HasApiTokens;
use Modules\Acl\App\Models\Group;
use Morilog\Jalali\Jalalian;
use Modules\Document\App\Models\Document;
use Modules\HR\App\Models\EmployeePosition;
use Modules\PettyCash\App\Models\PettyCash;
use Modules\Project\App\Models\Project;
use Modules\WBS\App\Models\Task;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use HasRoles;
    use Notifiable;

    /*
    |--------------------------------------------------------------------------
    | Model Configuration
    |--------------------------------------------------------------------------
    */

    protected $fillable = [
        'name',
        'mobile',
        'email',
        'password',
        'national_code',
        'personnel_code',
        'otp_code',
        'otp_expires_at',
        'settings',
        'avatar',
        'is_active',
        'employee_type',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'otp_code',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'otp_expires_at'    => 'datetime',
        'settings'          => 'array',
        'is_active' => 'boolean',
        'employee_type'     => 'string',
    ];


    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    public function is_active(): bool
    {
        return (bool) $this->is_active;
    }

    public function isInactive(): bool
    {
        return !$this->is_active;
    }


    public function isContractor(): bool
    {
        return $this->employee_type === 'contractor';
    }

    public function isPersonnel(): bool
    {
        return !$this->isContractor();
    }

    /**
     * آیا کاربر اجازه ورود به سیستم را دارد؟
     * پیمانکار حتی اگر فعال هم باشد نباید بتواند لاگین کند.
     */
    public function canLogin(): bool
    {
        return (bool) $this->is_active && !$this->isContractor();
    }

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

    public function managedProjects(): HasMany
    {
        return $this->hasMany(Project::class, 'manager_id');
    }

    public function assignedTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'assigned_to');
    }

    public function taskAssignments(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'task_assignees')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function pettyCashCustodies(): HasMany
    {
        return $this->hasMany(PettyCash::class, 'custodian_id');
    }

    public function uploadedDocuments(): HasMany
    {
        return $this->hasMany(Document::class, 'uploaded_by');
    }

    public function employeePosition(): HasOne
    {
        return $this->hasOne(EmployeePosition::class, 'user_id');
    }

    public function loginActivities(): HasMany
    {
        return $this->hasMany(LoginActivity::class)
            ->orderByDesc('login_at');
    }

    /*
    |--------------------------------------------------------------------------
    | Gtarabar Employee
    |--------------------------------------------------------------------------
    */

    /**
     * دریافت اطلاعات کارمند از گستراب
     */
    public function getGtarabarEmployee(): ?object
    {
        if (empty($this->personnel_code)) {
            return null;
        }

        try {
            return DB::connection('gtarabar')
                ->table('HCM3.Employee AS e')
                ->join(
                    'GNR3.Party AS p',
                    'p.PartyID',
                    '=',
                    'e.PartyRef'
                )
                ->where('e.Code', $this->personnel_code)
                ->select([
                    'e.EmployeeID',
                    'e.Code AS PersonnelCode',
                    'e.EmploymentNumber',
                    'p.FullName',
                    'p.NationalID',
                    'p.Mobile',
                    'p.Email',
                ])
                ->first();
        } catch (\Throwable $e) {
            Log::error('Gtarabar employee lookup failed', [
                'user_id'       => $this->id,
                'personnel_code'=> $this->personnel_code,
                'error'         => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * بررسی اینکه کاربر کارمند گستراب است
     */
    public function isGtarabarEmployee(): bool
    {
        return $this->getGtarabarEmployee() !== null;
    }

    /*
    |--------------------------------------------------------------------------
    | Payslip
    |--------------------------------------------------------------------------
    */

    /**
     * دریافت ماه‌های دارای فیش حقوقی
     */
    public function getAvailablePayslipMonths(): array
    {
        $employee = $this->getGtarabarEmployee();

        if (!$employee) {
            return [];
        }

        try {
            return DB::connection('gtarabar')
                ->table('HCM3.PayCalc')
                ->where('EmployeeRef', $employee->EmployeeID)
                ->distinct()
                ->orderByDesc('IssueYearMonth')
                ->pluck('IssueYearMonth')
                ->toArray();
        } catch (\Throwable $e) {
            Log::error('Payslip months lookup failed', [
                'user_id' => $this->id,
                'error'   => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * دریافت جزئیات فیش حقوقی
     */
    public function getPayslip(int $yearMonth): array
    {
        if (empty($this->personnel_code)) {
            return [];
        }

        try {
            return DB::connection('gtarabar')->select(
                "
                SELECT
                    E.Code AS PersonnelCode,
                    P.FullName AS EmployeeName,
                    pc.IssueYearMonth,
                    CF.Title AS ItemTitle,
                    CF.Name AS ItemName,

                    CASE
                        WHEN CF.Name IN ('UF461', 'UF462', 'UF454')
                            THEN pci.Value / 60.0
                        ELSE pci.Value
                    END AS Amount,

                    CASE
                        WHEN CF.Name IN (
                            'NetPay',
                            'BonusSum',
                            'DeductSum',
                            'Effectwork'
                        )
                            THEN 'جمع‌ها'

                        WHEN CF.Name IN (
                            'UF461',
                            'UF462',
                            'UF454'
                        )
                            THEN 'کارکرد'

                        WHEN CF.Name IN (
                            'EmployeeMainInsurance',
                            'Tax',
                            'kasremoaveghe',
                            'LoanSum',
                            'TaxGov'
                        )
                            THEN 'کسورات'

                        ELSE 'مزایا'
                    END AS Category

                FROM HCM3.PayCalc pc

                INNER JOIN HCM3.PayCalcItem pci
                    ON pc.PayCalcID = pci.PayCalcRef

                INNER JOIN HCM3.CompensationFactor CF
                    ON pci.CompensationFactorRef =
                       CF.CompensationFactorID

                INNER JOIN HCM3.Employee E
                    ON pc.EmployeeRef = E.EmployeeID

                INNER JOIN GNR3.Party P
                    ON P.PartyID = E.PartyRef

                WHERE pc.IssueYearMonth = ?
                  AND E.Code = ?
                  AND pci.Value <> 0

                  AND CF.Name IN (
                      'UF468',
                      'mozdsanavat',
                      'Housepay',
                      'bonkargari',
                      'ChildPay',
                      'UF534',
                      'ManagePAy',
                      'sakhtikar',
                      'UF520',
                      'UF533',
                      'ExtraworkPay',
                      'tatilkari',
                      'beinerahi',
                      'ayabzahab',
                      'UF541',
                      'EmployeeMainInsurance',
                      'Tax',
                      'kasremoaveghe',
                      'LoanSum',
                      'BonusSum',
                      'DeductSum',
                      'NetPay',
                      'Effectwork',
                      'UF461',
                      'UF462',
                      'UF454'
                  )

                ORDER BY
                    CASE CF.Name
                        WHEN 'Effectwork' THEN 1
                        WHEN 'UF461' THEN 2
                        WHEN 'UF462' THEN 3
                        WHEN 'UF454' THEN 4

                        WHEN 'UF468' THEN 10
                        WHEN 'mozdsanavat' THEN 11
                        WHEN 'Housepay' THEN 12
                        WHEN 'bonkargari' THEN 13
                        WHEN 'ChildPay' THEN 14
                        WHEN 'UF534' THEN 15
                        WHEN 'ManagePAy' THEN 16
                        WHEN 'sakhtikar' THEN 17
                        WHEN 'UF520' THEN 18
                        WHEN 'UF533' THEN 19
                        WHEN 'ExtraworkPay' THEN 20
                        WHEN 'tatilkari' THEN 21
                        WHEN 'beinerahi' THEN 22
                        WHEN 'ayabzahab' THEN 23
                        WHEN 'UF541' THEN 24

                        WHEN 'BonusSum' THEN 30
                        WHEN 'EmployeeMainInsurance' THEN 40
                        WHEN 'Tax' THEN 41
                        WHEN 'kasremoaveghe' THEN 42
                        WHEN 'LoanSum' THEN 43
                        WHEN 'TaxGov' THEN 44
                        WHEN 'DeductSum' THEN 50
                        WHEN 'NetPay' THEN 60

                        ELSE 99
                    END
                ",
                [
                    $yearMonth,
                    $this->personnel_code,
                ]
            );
        } catch (\Throwable $e) {
            Log::error('Payslip lookup failed', [
                'user_id'    => $this->id,
                'year_month' => $yearMonth,
                'error'      => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * دریافت خلاصه فیش حقوقی
     */
    public function getPayslipSummary(int $yearMonth): ?object
    {
        if (empty($this->personnel_code)) {
            return null;
        }

        try {
            return DB::connection('gtarabar')->selectOne(
                "
                SELECT
                    E.Code AS PersonnelCode,
                    P.FullName AS EmployeeName,
                    P.NationalID,
                    pc.IssueYearMonth,

                    SUM(
                        CASE
                            WHEN CF.Name = 'BonusSum'
                                THEN pci.Value
                            ELSE 0
                        END
                    ) AS TotalBenefits,

                    SUM(
                        CASE
                            WHEN CF.Name = 'DeductSum'
                                THEN pci.Value
                            ELSE 0
                        END
                    ) AS TotalDeductions,

                    SUM(
                        CASE
                            WHEN CF.Name = 'NetPay'
                                THEN pci.Value
                            ELSE 0
                        END
                    ) AS NetPay

                FROM HCM3.PayCalc pc

                INNER JOIN HCM3.PayCalcItem pci
                    ON pc.PayCalcID = pci.PayCalcRef

                INNER JOIN HCM3.CompensationFactor CF
                    ON pci.CompensationFactorRef =
                       CF.CompensationFactorID

                INNER JOIN HCM3.Employee E
                    ON pc.EmployeeRef = E.EmployeeID

                INNER JOIN GNR3.Party P
                    ON P.PartyID = E.PartyRef

                WHERE pc.IssueYearMonth = ?
                  AND E.Code = ?

                  AND CF.Name IN (
                      'BonusSum',
                      'DeductSum',
                      'NetPay'
                  )

                GROUP BY
                    E.Code,
                    P.FullName,
                    P.NationalID,
                    pc.IssueYearMonth
                ",
                [
                    $yearMonth,
                    $this->personnel_code,
                ]
            );
        } catch (\Throwable $e) {
            Log::error('Payslip summary lookup failed', [
                'user_id'    => $this->id,
                'year_month' => $yearMonth,
                'error'      => $e->getMessage(),
            ]);

            return null;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Employee Statute / Position
    |--------------------------------------------------------------------------
    */

    /**
     * دریافت آخرین حکم کارگزینی
     */
    /**
     * دریافت آخرین حکم کارگزینی
     */
    public function getEmployeeStatute(): ?object
    {
        if (empty($this->personnel_code)) {
            return null;
        }

        try {
            return DB::connection('gtarabar')->selectOne(
                "
            SELECT TOP 1
                es.EmployeeStatuteID,
                es.EmployeeRef,
                es.PostRef,
                p.Code AS PostCode,
                p.Title AS PostTitle,
                es.JobRef,
                j.Code AS JobCode,
                j.Title AS JobTitle,
                es.DepartmentRef,
                d.Title AS DepartmentTitle,
                es.OrganizationalStructureRef
            FROM HCM3.EmployeeStatute es
            LEFT JOIN HCM3.Post p
                ON p.PostID = es.PostRef
            LEFT JOIN HCM3.Job j
                ON j.JobID = es.JobRef
            LEFT JOIN HCM3.Department d
                ON d.DepartmentID = es.DepartmentRef
            INNER JOIN HCM3.Employee e
                ON e.EmployeeID = es.EmployeeRef
            WHERE e.Code = ?
            ORDER BY es.EmployeeStatuteID DESC
            ",
                [$this->personnel_code]
            );
        } catch (\Throwable $e) {
            Log::error('Employee statute lookup failed', [
                'user_id' => $this->id,
                'error'   => $e->getMessage(),
            ]);

            return null;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Dashboard Profile
    |--------------------------------------------------------------------------
    */

    /**
     * اطلاعات پروفایل مورد نیاز داشبورد
     */
    public function getDashboardProfile(): array
    {
        $employee = $this->getGtarabarEmployee();
        $statute  = $this->getEmployeeStatute();

        return [
            'id'              => $this->id,
            'name'            => $this->name,
            'mobile'          => $this->mobile,
            'email'           => $this->email,
            'personnel_code'  => $this->personnel_code,
            'national_code'   => $this->national_code,

            'employee_id'     => $employee?->EmployeeID,
            'employment_number' => $employee?->EmploymentNumber,

            'post' => [
                'code'  => $statute?->PostCode,
                'title' => $statute?->PostTitle,
            ],

            'job' => [
                'code'  => $statute?->JobCode,
                'title' => $statute?->JobTitle,
            ],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Leave
    |--------------------------------------------------------------------------
    */

    /**
     * دریافت مانده انتقالی مرخصی
     */
    public function getLeaveRemainder(?int $beforeJalaliYear = null): array
    {
        $employee = $this->getGtarabarEmployee();

        if (!$employee) {
            return [];
        }

        try {
            $query = DB::connection('gtarabar')
                ->table('HCM3.LeaveRemainder AS lr')
                ->join(
                    'HCM3.LeaveType AS lt',
                    'lt.LeaveTypeID',
                    '=',
                    'lr.LeaveTypeRef'
                )
                ->where('lr.EmployeeRef', $employee->EmployeeID)
                ->where('lr.LeaveTypeRef', 1)
                ->where('lr.IsInactive', 0);

            if ($beforeJalaliYear) {
                $query->where(
                    'lr.EffectiveYearMonth',
                    '<',
                    $beforeJalaliYear * 100 + 1
                );
            }

            $remainder = $query
                ->select([
                    'lt.Title AS LeaveTypeName',
                    'lr.EffectiveYearMonth',
                    'lr.Value AS RemainderMinutes',
                    DB::raw(
                        'CAST(lr.Value AS FLOAT) / 440.0 AS RemainderDays'
                    ),
                    'lr.EffectiveDate',
                ])
                ->orderByDesc('lr.EffectiveYearMonth')
                ->first();

            if (!$remainder) {
                return [];
            }

            $year  = intdiv(
                (int) $remainder->EffectiveYearMonth,
                100
            );

            $month = (int) $remainder->EffectiveYearMonth % 100;

            $persianMonths = $this->getPersianMonths();

            return [
                'leave_type'       => $remainder->LeaveTypeName,
                'year'             => $year,
                'month'            => $month,
                'month_name'       => $persianMonths[$month] ?? '',
                'remainder_minutes'=> (int) $remainder->RemainderMinutes,
                'remainder_days'   => round(
                    (float) $remainder->RemainderDays,
                    2
                ),
                'effective_date'   => $remainder->EffectiveDate,
            ];
        } catch (\Throwable $e) {
            Log::error('Leave remainder lookup failed', [
                'user_id' => $this->id,
                'error'   => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * تعداد ماه‌های دارای فیش حقوقی
     */
    public function getProcessedMonthsCount(
        int $jalaliYear,
        array $payslipMonths = []
    ): int {
        if (!empty($payslipMonths)) {
            return count(
                array_filter(
                    $payslipMonths,
                    fn ($month) =>
                        intdiv((int) $month, 100) === $jalaliYear
                )
            );
        }

        $employee = $this->getGtarabarEmployee();

        if (!$employee) {
            return 0;
        }

        try {
            return (int) DB::connection('gtarabar')
                ->table('HCM3.PayCalc')
                ->where(
                    'EmployeeRef',
                    $employee->EmployeeID
                )
                ->whereBetween(
                    'IssueYearMonth',
                    [
                        $jalaliYear * 100 + 1,
                        $jalaliYear * 100 + 12,
                    ]
                )
                ->distinct()
                ->count('IssueYearMonth');
        } catch (\Throwable $e) {
            Log::error('Processed months lookup failed', [
                'user_id' => $this->id,
                'year'    => $jalaliYear,
                'error'   => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * دریافت مرخصی‌های استفاده‌شده در بازه
     */
    public function getUsedLeavesCurrentMonth(
        string $startDate,
        string $endDate
    ): array {
        $employee = $this->getGtarabarEmployee();

        if (!$employee) {
            return [];
        }

        try {
            return DB::connection('gtarabar')
                ->table('HCM3.LeaveRequest AS lr')
                ->join(
                    'HCM3.LeaveType AS lt',
                    'lt.LeaveTypeID',
                    '=',
                    'lr.LeaveTypeRef'
                )
                ->where(
                    'lr.EmployeeRef',
                    $employee->EmployeeID
                )
                ->where('lr.FromDateTime', '<=', $endDate)
                ->where('lr.ToDateTime', '>=', $startDate)
                ->where('lr.Status', 10)
                ->select([
                    'lr.LeaveRequestID',
                    'lr.RequestNumber',
                    'lt.Title AS LeaveTypeName',
                    'lr.FromDateTime',
                    'lr.ToDateTime',
                    'lr.IsDaily',
                    'lr.Status',
                    'lr.Reason',
                ])
                ->selectRaw(
                    'DATEDIFF(minute, lr.FromDateTime, lr.ToDateTime)
                     AS DurationMinutes'
                )
                ->selectRaw(
                    "
                    CASE
                        WHEN lr.IsDaily = 1
                            THEN DATEDIFF(
                                day,
                                CASE
                                    WHEN lr.FromDateTime < ?
                                        THEN ?
                                    ELSE lr.FromDateTime
                                END,
                                CASE
                                    WHEN lr.ToDateTime > ?
                                        THEN ?
                                    ELSE lr.ToDateTime
                                END
                            ) + 1

                        ELSE CAST(
                            DATEDIFF(
                                minute,
                                CASE
                                    WHEN lr.FromDateTime < ?
                                        THEN ?
                                    ELSE lr.FromDateTime
                                END,
                                CASE
                                    WHEN lr.ToDateTime > ?
                                        THEN ?
                                    ELSE lr.ToDateTime
                                END
                            ) AS FLOAT
                        ) / 440.0
                    END AS DaysCountInMonth
                    ",
                    [
                        $startDate,
                        $startDate,
                        $endDate,
                        $endDate,
                        $startDate,
                        $startDate,
                        $endDate,
                        $endDate,
                    ]
                )
                ->orderByDesc('lr.FromDateTime')
                ->get()
                ->toArray();
        } catch (\Throwable $e) {
            Log::error('Used leaves lookup failed', [
                'user_id' => $this->id,
                'error'   => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * مجموع مرخصی استفاده‌شده در بازه
     */
    public function getTotalUsedLeavesCurrentMonth(
        string $startDate,
        string $endDate
    ): float {
        return $this->calculateUsedLeaves(
            $startDate,
            $endDate
        );
    }

    /**
     * مجموع مرخصی استفاده‌شده در سال
     */
    public function getTotalUsedLeavesCurrentYear(
        string $startDate,
        string $endDate
    ): float {
        return $this->calculateUsedLeaves(
            $startDate,
            $endDate
        );
    }

    /**
     * محاسبه مجموع مرخصی استفاده‌شده
     */
    protected function calculateUsedLeaves(
        string $startDate,
        string $endDate
    ): float {
        $employee = $this->getGtarabarEmployee();

        if (!$employee) {
            return 0.0;
        }

        try {
            $total = DB::connection('gtarabar')
                ->table('HCM3.LeaveRequest AS lr')
                ->where(
                    'lr.EmployeeRef',
                    $employee->EmployeeID
                )
                ->where('lr.FromDateTime', '<=', $endDate)
                ->where('lr.ToDateTime', '>=', $startDate)
                ->where('lr.Status', 10)
                ->selectRaw(
                    "
                    SUM(
                        CASE
                            WHEN lr.IsDaily = 1
                                THEN DATEDIFF(
                                    day,
                                    CASE
                                        WHEN lr.FromDateTime < ?
                                            THEN ?
                                        ELSE lr.FromDateTime
                                    END,
                                    CASE
                                        WHEN lr.ToDateTime > ?
                                            THEN ?
                                        ELSE lr.ToDateTime
                                    END
                                ) + 1

                            ELSE CAST(
                                DATEDIFF(
                                    minute,
                                    CASE
                                        WHEN lr.FromDateTime < ?
                                            THEN ?
                                        ELSE lr.FromDateTime
                                    END,
                                    CASE
                                        WHEN lr.ToDateTime > ?
                                            THEN ?
                                        ELSE lr.ToDateTime
                                    END
                                ) AS FLOAT
                            ) / 440.0
                        END
                    ) AS TotalDays
                    ",
                    [
                        $startDate,
                        $startDate,
                        $endDate,
                        $endDate,
                        $startDate,
                        $startDate,
                        $endDate,
                        $endDate,
                    ]
                )
                ->value('TotalDays');

            return round((float) ($total ?? 0), 2);
        } catch (\Throwable $e) {
            Log::error('Used leave total lookup failed', [
                'user_id' => $this->id,
                'error'   => $e->getMessage(),
            ]);

            return 0.0;
        }
    }

    /**
     * اطلاعات کامل مرخصی برای داشبورد
     */
    public function getLeaveDashboardData(
        ?int $latestPayslipYearMonth = null,
        array $payslipMonths = []
    ): array {
        $employee = $this->getGtarabarEmployee();

        if (!$employee) {
            return [];
        }

        $latestPayslipYearMonth =
            $latestPayslipYearMonth
            ?? ($payslipMonths[0] ?? null);

        if ($latestPayslipYearMonth) {
            $jalaliYear  = intdiv(
                $latestPayslipYearMonth,
                100
            );

            $jalaliMonth =
                $latestPayslipYearMonth % 100;
        } else {
            $now = Jalalian::now();

            $jalaliYear  = $now->getYear();
            $jalaliMonth = $now->getMonth();
        }

        $processedMonths = $this->getProcessedMonthsCount(
            $jalaliYear,
            $payslipMonths
        );

        $carryOver = $this->getLeaveRemainder(
            $jalaliYear
        );

        $monthCarbon = Jalalian::fromFormat(
            'Y-m-d',
            sprintf(
                '%04d-%02d-01',
                $jalaliYear,
                $jalaliMonth
            )
        )->toCarbon();

        $monthStartDate = $monthCarbon
            ->copy()
            ->startOfDay()
            ->format('Y-m-d H:i:s');

        $monthEndDate = $monthCarbon
            ->copy()
            ->endOfMonth()
            ->endOfDay()
            ->format('Y-m-d H:i:s');

        $yearStartDate = Jalalian::fromFormat(
            'Y-m-d',
            sprintf(
                '%04d-01-01',
                $jalaliYear
            )
        )
            ->toCarbon()
            ->startOfDay()
            ->format('Y-m-d H:i:s');

        $usedLeaves = $this->getUsedLeavesCurrentMonth(
            $monthStartDate,
            $monthEndDate
        );

        $totalUsedThisMonth =
            $this->getTotalUsedLeavesCurrentMonth(
                $monthStartDate,
                $monthEndDate
            );

        $totalUsedThisYear =
            $this->getTotalUsedLeavesCurrentYear(
                $yearStartDate,
                $monthEndDate
            );

        /*
         * سهم ماهانه:
         * 2 روز + 4 ساعت
         * هر روز کاری = 440 دقیقه
         */
        $monthlyEntitlementMinutes =
            (2 * 440) + (4 * 60);

        $carryOverDays =
            $carryOver['remainder_days'] ?? 0;

        $earnedDays =
            ($processedMonths * $monthlyEntitlementMinutes) / 440;

        $totalEntitled =
            $carryOverDays + $earnedDays;

        $currentRemainder =
            $totalEntitled - $totalUsedThisYear;

        return [
            'remainder' => $carryOver,

            'used_leaves_current_month' =>
                $usedLeaves,

            'summary' => [
                'processed_year' =>
                    $jalaliYear,

                'processed_month' =>
                    $jalaliMonth,

                'processed_month_name' =>
                    $this->getPersianMonths()[$jalaliMonth] ?? '',

                'processed_months_count' =>
                    $processedMonths,

                'carry_over_days' =>
                    round($carryOverDays, 2),

                'earned_days_this_year' =>
                    round($earnedDays, 2),

                'total_entitled_days' =>
                    round($totalEntitled, 2),

                'used_days_this_month' =>
                    round($totalUsedThisMonth, 2),

                'current_remainder_days' =>
                    round($currentRemainder, 2),

                'current_remainder_human' =>
                    self::formatDaysHuman(
                        $currentRemainder
                    ),

                'used_days_this_year' =>
                    round($totalUsedThisYear, 2),

                'used_this_year_human' =>
                    self::formatDaysHuman(
                        $totalUsedThisYear
                    ),

                'total_entitled_human' =>
                    self::formatDaysHuman(
                        $totalEntitled
                    ),
            ],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * تبدیل روز اعشاری به فرمت انسانی
     */
    public static function formatDaysHuman(
        float $days
    ): string {
        $totalMinutes =
            (int) round($days * 440);

        $day =
            intdiv($totalMinutes, 440);

        $remaining =
            $totalMinutes % 440;

        $hour =
            intdiv($remaining, 60);

        $minute =
            $remaining % 60;

        $parts = [];

        if ($day > 0) {
            $parts[] = "{$day} روز";
        }

        if ($hour > 0) {
            $parts[] = "{$hour} ساعت";
        }

        if ($minute > 0) {
            $parts[] = "{$minute} دقیقه";
        }

        return implode(' و ', $parts) ?: '0 روز';
    }

    /**
     * نام ماه‌های شمسی
     */
    protected function getPersianMonths(): array
    {
        return [
            1  => 'فروردین',
            2  => 'اردیبهشت',
            3  => 'خرداد',
            4  => 'تیر',
            5  => 'مرداد',
            6  => 'شهریور',
            7  => 'مهر',
            8  => 'آبان',
            9  => 'آذر',
            10 => 'دی',
            11 => 'بهمن',
            12 => 'اسفند',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Login Activities / Sessions
    |--------------------------------------------------------------------------
    */

    /**
     * دریافت آخرین فعالیت‌های ورود
     */
    public function getRecentLoginActivities(
        int $limit = 10
    ): array {
        return $this->loginActivities()
            ->limit($limit)
            ->get()
            ->map(
                function (LoginActivity $activity): array {
                    return [
                        'id' =>
                            $activity->id,

                        'ip' =>
                            $activity->ip_address,

                        'browser' =>
                            $activity->browser,

                        'os' =>
                            $activity->os,

                        'device_type' =>
                            $activity->device_type,

                        'location' =>
                            $activity->location ?? 'نامشخص',

                        'time' =>
                            $activity->login_at
                                ?->diffForHumans(
                                    null,
                                    true
                                ),

                        'full_time' =>
                            $activity->login_at
                                ?->format(
                                    'Y-m-d H:i:s'
                                ),

                        'is_current' =>
                            (bool) $activity->is_current,
                    ];
                }
            )
            ->toArray();
    }

    /**
     * دریافت نشست‌های فعال
     */
    public function getActiveSessions(): array
    {
        $activities = $this->loginActivities()
            ->limit(10)
            ->get();

        return $activities
            ->map(
                function (
                    LoginActivity $activity
                ): array {
                    return [
                        'id' =>
                            $activity->id,

                        'ip' =>
                            $activity->ip_address
                            ?? 'نامشخص',

                        'browser' =>
                            $activity->browser
                            ?? 'نامشخص',

                        'os' =>
                            $activity->os
                            ?? 'نامشخص',

                        'device_type' =>
                            $activity->device_type
                            ?? 'desktop',

                        'location' =>
                            $activity->location
                            ?? 'نامشخص',

                        'last_used_at' =>
                            $activity->login_at
                                ?->diffForHumans(
                                    null,
                                    true
                                )
                            ?? 'نامشخص',

                        'created_at' =>
                            $activity->created_at
                                ?->format(
                                    'Y-m-d H:i'
                                )
                            ?? 'نامشخص',

                        'is_current' =>
                            (bool) $activity->is_current,
                    ];
                }
            )
            ->toArray();
    }

    /**
     * حذف یک نشست
     */
    public function revokeSession(
        int $activityId
    ): bool {
        $activity = LoginActivity::query()
            ->where('user_id', $this->id)
            ->where('id', $activityId)
            ->first();

        if (!$activity) {
            return false;
        }

        if ($activity->is_current) {
            return false;
        }

        $activity->update([
            'is_current' => false,
        ]);

        return true;
    }

    /**
     * خروج از سایر دستگاه‌ها
     */
    public function revokeAllSessions(): void
    {
        LoginActivity::query()
            ->where('user_id', $this->id)
            ->where('is_current', true)
            ->where(
                'id',
                '!=',
                $this->getCurrentActivityId()
            )
            ->update([
                'is_current' => false,
            ]);
    }

    /**
     * دریافت شناسه فعالیت فعلی
     */
    protected function getCurrentActivityId(): ?int
    {
        return LoginActivity::query()
            ->where('user_id', $this->id)
            ->where('is_current', true)
            ->orderByDesc('login_at')
            ->value('id');
    }





    /*
    |--------------------------------------------------------------------------
    | Groups (ACL)
    |--------------------------------------------------------------------------
    */
    public function getAllRolesCollection(): \Illuminate\Support\Collection
    {
        $roles = \DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', get_class($this))
            ->where('model_has_roles.model_id', $this->id)
            ->get(['roles.id', 'roles.name', 'model_has_roles.source']);

        return $roles->map(function ($r) {
            return [
                'id'     => $r->id,
                'name'   => $r->name,
                'source' => $r->source, // 'direct' یا 'group:X'
            ];
        });
    }



    /*
    |--------------------------------------------------------------------------
    | Groups (ماژول Acl)
    |--------------------------------------------------------------------------
    */
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'group_user')
            ->withPivot(['assigned_by', 'assigned_at', 'note'])
            ->withTimestamps();
    }

    /**
     * دریافت همه نقش‌ها با مشخص شدن منبع (مستقیم یا از گروه)
     */
    public function getAllRolesWithSource(): array
    {
        $rows = \DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', get_class($this))
            ->where('model_has_roles.model_id', $this->id)
            ->get(['roles.id', 'roles.name', 'model_has_roles.source']);

        return $rows->map(fn ($r) => [
            'id'     => $r->id,
            'name'   => $r->name,
            'source' => $r->source, // 'direct' یا 'group:X'
        ])->toArray();
    }



}

