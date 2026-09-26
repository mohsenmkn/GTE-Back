<?php

// Modules/Payroll/App/Repositories/PayrollRepository.php

namespace Modules\Payroll\App\Repositories;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class PayrollRepository
{
    private string $connection = 'gtarabar';

    /**
     * دریافت اطلاعات کارمند از کد پرسنلی
     */
    public function findEmployeeByCode(string $personnelCode): ?object
    {
        return DB::connection($this->connection)
            ->table('HCM3.Employee AS e')
            ->join('GNR3.Party AS p', 'p.PartyID', '=', 'e.PartyRef')
            ->where('e.Code', $personnelCode)
            ->select(
                'e.EmployeeID',
                'e.Code AS PersonnelCode',
                'e.EmploymentNumber',
                'p.FullName',
                'p.NationalID',
                'p.FatherName',
                'p.Mobile',
                'p.Email'
            )
            ->first();
    }

    /**
     * دریافت لیست ماه‌های موجود برای یک کارمند
     */
    public function getAvailableMonths(int $employeeId): array
    {
        return DB::connection($this->connection)
            ->table('HCM3.PayCalc')
            ->where('EmployeeRef', $employeeId)
            ->select('IssueYearMonth')
            ->distinct()
            ->orderBy('IssueYearMonth', 'desc')
            ->pluck('IssueYearMonth')
            ->toArray();
    }

    /**
     * دریافت اقلام فیش حقوقی
     */
    public function getPayslipItems(int $employeeId, int $yearMonth): array
    {
        $cacheKey = "payslip_items_{$employeeId}_{$yearMonth}";

        return Cache::remember($cacheKey, now()->addHours(6), function () use ($employeeId, $yearMonth) {
            return DB::connection($this->connection)->select("
                SELECT
                    E.Code AS PersonnelCode,
                    P.FullName AS EmployeeName,
                    P.NationalID,
                    P.FatherName,
                    pc.IssueYearMonth,
                    CF.Title AS ItemTitle,
                    CF.Name AS ItemName,

                    CASE
                        WHEN CF.Name IN ('UF461', 'UF462', 'UF454', 'UF455') THEN pci.Value / 60.0
                        WHEN CF.Name = 'UF636' THEN -pci.Value
                        ELSE pci.Value
                    END AS Amount,

                    CASE
                        WHEN CF.Name IN ('NetPay', 'BonusSum', 'DeductSum', 'Effectwork') THEN N'جمع‌ها'
                        WHEN CF.Name IN ('UF461', 'UF462', 'UF454', 'UF455', 'UF517') THEN N'کارکرد'
                        WHEN CF.Name IN (
                            'EmployeeMainInsurance',
                            'Tax',
                            'kasremoaveghe',
                            'LoanSum',
                            'UF636'
                        ) THEN N'کسورات'
                        ELSE N'مزایا'
                    END AS Category

                FROM HCM3.PayCalc pc
                INNER JOIN HCM3.PayCalcItem pci ON pc.PayCalcID = pci.PayCalcRef
                INNER JOIN HCM3.CompensationFactor CF ON pci.CompensationFactorRef = CF.CompensationFactorID
                INNER JOIN HCM3.Employee E ON pc.EmployeeRef = E.EmployeeID
                INNER JOIN GNR3.Party P ON P.PartyID = E.PartyRef

                WHERE pc.IssueYearMonth = ?
                  AND E.EmployeeID = ?
                  AND pci.Value <> 0
                  AND CF.Name IN (
                    -- حقوق پایه و مزایای ثابت
                    'UF468', 'mozdsanavat', 'Housepay', 'bonkargari', 'ChildPay', 'UF534',
                    'ManagePAy', 'sakhtikar', 'UF520', 'UF533',

                    -- کارکرد و اضافه‌کار
                    'ExtraworkPay', 'tatilkari', 'beinerahi', 'ayabzahab',

                    -- 🔑 جمعه کاری و ماموریت (جدید)
                    'jomekari', 'mamoriat',

                    -- پاداش کارانه
                    'UF541',

                    -- سایر مزایا
                    'Otherbenefit',

                    -- کسورات
                    'EmployeeMainInsurance', 'Tax', 'kasremoaveghe', 'LoanSum', 'UF636',

                    -- جمع‌ها
                    'BonusSum', 'DeductSum', 'NetPay',

                    -- کارکرد (دقیقه)
                    'Effectwork', 'UF461', 'UF462', 'UF454',

                    -- 🔑 کارکرد جمعه کاری و ماموریت (جدید)
                    'UF455', 'UF517'
                  )
                ORDER BY
                    CASE CF.Name
                        -- کارکرد (دقیقه/روز)
                        WHEN 'Effectwork' THEN 1
                        WHEN 'UF461' THEN 2
                        WHEN 'UF462' THEN 3
                        WHEN 'UF454' THEN 4
                        WHEN 'UF455' THEN 5      -- 🔑 کارکرد جمعه کاری
                        WHEN 'UF517' THEN 6      -- 🔑 کارکرد ماموریت

                        -- حقوق پایه و مزایای ثابت
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

                        -- کارکرد متغیر (مبلغ)
                        WHEN 'ExtraworkPay' THEN 20
                        WHEN 'tatilkari' THEN 21
                        WHEN 'beinerahi' THEN 22
                        WHEN 'ayabzahab' THEN 23

                        -- 🔑 جمعه کاری و ماموریت (مبلغ)
                        WHEN 'jomekari' THEN 24
                        WHEN 'mamoriat' THEN 25

                        -- پاداش کارانه
                        WHEN 'UF541' THEN 26

                        WHEN 'Otherbenefit' THEN 27

                        -- جمع مزایا
                        WHEN 'BonusSum' THEN 30

                        -- کسورات
                        WHEN 'EmployeeMainInsurance' THEN 40
                        WHEN 'Tax' THEN 41
                        WHEN 'kasremoaveghe' THEN 42
                        WHEN 'LoanSum' THEN 43
                        WHEN 'TaxGov' THEN 44
                        WHEN 'UF636' THEN 45

                        -- جمع کسورات
                        WHEN 'DeductSum' THEN 50

                        -- خالص
                        WHEN 'NetPay' THEN 60

                        ELSE 99
                    END
            ", [$yearMonth, $employeeId]);
        });
    }

    /**
     * دریافت خلاصه فیش حقوقی (فقط جمع‌ها)
     */
    public function getPayslipSummary(int $employeeId, int $yearMonth): ?object
    {
        return DB::connection($this->connection)->selectOne("
            SELECT
                E.Code AS PersonnelCode,
                P.FullName AS EmployeeName,
                P.NationalID,
                P.FatherName,
                pc.IssueYearMonth,
                SUM(CASE WHEN CF.Name = 'Effectwork' THEN pci.Value ELSE 0 END) AS EffectiveDays,
                SUM(CASE WHEN CF.Name = 'BonusSum' THEN pci.Value ELSE 0 END) AS TotalBenefits,
                SUM(
                    CASE
                        WHEN CF.Name IN ('EmployeeMainInsurance', 'Tax', 'kasremoaveghe', 'LoanSum')
                        THEN pci.Value
                        WHEN CF.Name = 'UF636' THEN -pci.Value
                        ELSE 0
                    END
                ) AS TotalDeductions,
                SUM(CASE WHEN CF.Name = 'NetPay' THEN pci.Value ELSE 0 END) AS NetPay
            FROM HCM3.PayCalc pc
            INNER JOIN HCM3.PayCalcItem pci ON pc.PayCalcID = pci.PayCalcRef
            INNER JOIN HCM3.CompensationFactor CF ON pci.CompensationFactorRef = CF.CompensationFactorID
            INNER JOIN HCM3.Employee E ON pc.EmployeeRef = E.EmployeeID
            INNER JOIN GNR3.Party P ON P.PartyID = E.PartyRef
            WHERE pc.IssueYearMonth = ?
              AND E.EmployeeID = ?
              AND CF.Name IN (
                'Effectwork', 'BonusSum', 'DeductSum', 'NetPay',
                'EmployeeMainInsurance', 'Tax', 'kasremoaveghe', 'LoanSum','UF636')
            GROUP BY E.Code, P.FullName, P.NationalID, P.FatherName, pc.IssueYearMonth
        ", [$yearMonth, $employeeId]);
    }

    /**
     * دریافت لیست کارمندان (برای مدیران/HR)
     */
    public function getEmployeesList(?string $search = null, int $perPage = 20): object
    {
        $query = DB::connection($this->connection)
            ->table('HCM3.Employee AS e')
            ->join('GNR3.Party AS p', 'p.PartyID', '=', 'e.PartyRef')
            ->select(
                'e.EmployeeID',
                'e.Code AS PersonnelCode',
                'p.FullName',
                'p.NationalID',
                'p.Mobile'
            );

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('e.Code', 'LIKE', "%{$search}%")
                    ->orWhere('p.FullName', 'LIKE', "%{$search}%")
                    ->orWhere('p.NationalID', 'LIKE', "%{$search}%");
            });
        }

        return $query->orderBy('e.Code')->paginate($perPage);
    }

    /**
     * پاک کردن کش فیش حقوقی
     */
    public function clearPayslipCache(int $employeeId, ?int $yearMonth = null): void
    {
        if ($yearMonth) {
            Cache::forget("payslip_items_{$employeeId}_{$yearMonth}");
        } else {
            $months = $this->getAvailableMonths($employeeId);
            foreach ($months as $month) {
                Cache::forget("payslip_items_{$employeeId}_{$month}");
            }
        }
    }
}
