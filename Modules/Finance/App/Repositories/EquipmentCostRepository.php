<?php

namespace Modules\Finance\App\Repositories;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Modules\Finance\App\Models\EquipmentTypeAccess;

class EquipmentCostRepository
{
    const EQUIPMENT_TYPES = [
        49 => 'لودر',
        51 => 'بیل مکانیکی',
        77 => 'دامپتراک',
        78 => 'دریل حفاری',
        81 => 'شاول',
        24 => 'کامیون',
        26 => 'خودرو',
        27 => 'لوکوموتیو',
        40 => 'موتور سیکلت',
        50 => 'واگن باری',
        1  => 'شخص',
       // 9  => 'تنخواه',
    ];

    const CONFIRMED_VOUCHER_STATES = [4, 32];

    /**
     * دریافت انواع تجهیزاتی که کاربر دسترسی دارد
     */
    public function getAccessibleEquipmentTypes(?int $userId = null): array
    {
        $userId = $userId ?? Auth::id();
        $user = Auth::user();

        // اگر کاربر دسترسی مدیریت دارد، همه انواع را ببیند
        if ($user && $user->can('equipment_costs.manage')) {
            return $this->getAllEquipmentTypesFromDB();
        }

        if (!$user) {
            return [];
        }

        $roleIds = $user->roles ? $user->roles->pluck('id')->toArray() : [];

        $accessibleTypeIds = EquipmentTypeAccess::where(function ($q) use ($userId, $roleIds) {
            $q->where('user_id', $userId);
            if (!empty($roleIds)) {
                $q->orWhereIn('role_id', $roleIds);
            }
        })
            ->where('can_view', true)
            ->pluck('dl_type_ref')
            ->unique()
            ->toArray();

        if (empty($accessibleTypeIds)) {
            return [];
        }

        return DB::connection('gtarabar')
            ->table('FIN3.DLType')
            ->whereIn('DLTypeID', $accessibleTypeIds)
            ->select('DLTypeID', 'Title', 'Title_En')
            ->orderBy('Title')
            ->get()
            ->toArray();
    }

    /**
     * دریافت همه انواع تجهیز از دیتابیس راهکاران
     */
    public function getAllEquipmentTypesFromDB(): array
    {
        return DB::connection('gtarabar')
            ->table('FIN3.DLType as dt')
            ->join('FIN3.DL as dl', 'dl.DLTypeRef', '=', 'dt.DLTypeID')
            ->select('dt.DLTypeID', 'dt.Title', 'dt.Title_En')
            ->distinct()
            ->orderBy('dt.Title')
            ->get()
            ->toArray();
    }

    /**
     * ✅ متد جدید: دریافت تجهیزات بر اساس نوع (با چک دسترسی)
     */
    public function getEquipmentsByType(int $typeId): array
    {
        // بررسی دسترسی کاربر به این نوع تجهیز
        if (!$this->canAccessEquipmentType($typeId)) {
            abort(403, 'شما دسترسی به این نوع تجهیز را ندارید');
        }

        return DB::connection('gtarabar')
            ->table('FIN3.DL')
            ->where('DLTypeRef', $typeId)
            ->select('DLID', 'Code', 'Title', 'ReferenceID', 'Description')
            ->orderBy('Code')
            ->get()
            ->toArray();
    }

    /**
     * بررسی دسترسی کاربر به یک نوع تجهیز خاص
     */
    public function canAccessEquipmentType(int $dlTypeRef, ?int $userId = null): bool
    {
        $userId = $userId ?? Auth::id();
        $user = Auth::user();

        // admin یا manage permission = دسترسی کامل
        if ($user && $user->can('equipment_costs.manage')) {
            return true;
        }

        if (!$user) {
            return false;
        }

        $roleIds = $user->roles ? $user->roles->pluck('id')->toArray() : [];

        return EquipmentTypeAccess::where('dl_type_ref', $dlTypeRef)
            ->where('can_view', true)
            ->where(function ($q) use ($userId, $roleIds) {
                $q->where('user_id', $userId);
                if (!empty($roleIds)) {
                    $q->orWhereIn('role_id', $roleIds);
                }
            })
            ->exists();
    }

    /**
     * دریافت هزینه‌های یک تجهیز خاص
     */
    public function getEquipmentCosts(
        string $equipmentCode,
        int $typeId,
        string $fromDate,
        string $toDate
    ): array {
        if (!$this->canAccessEquipmentType($typeId)) {
            abort(403, 'شما دسترسی به این نوع تجهیز را ندارید');
        }

        // اجبار به رشته برای جلوگیری از تبدیل به عدد
        $equipmentCode = (string) $equipmentCode;

        return DB::connection('gtarabar')
            ->table('FIN3.Voucher as v')
            ->join('FIN3.VoucherItem as vi', 'v.VoucherID', '=', 'vi.VoucherRef')
            ->join('FIN3.SL as sl', 'vi.SLRef', '=', 'sl.SLID')
            ->join('FIN3.DL as dl', function ($join) {
                $join->on('dl.Code', '=', 'vi.DLLevel5')
                    ->whereColumn('dl.DLTypeRef', '=', 'vi.DLTypeRef5');
            })
            ->whereDate('v.Date', '>=', $fromDate)
            ->whereDate('v.Date', '<=', $toDate)
            ->where('vi.DLLevel5', '=', $equipmentCode)
            ->where('vi.DLTypeRef5', '=', $typeId)
            ->select(
                'v.VoucherID',
                'v.Number as voucher_number',
                'v.Date as voucher_date',
                'v.State as voucher_state',
                'v.Description as voucher_description',
                'vi.RowNumber as row_number',
                'sl.Title as account_title',
                'sl.Code as account_code',
                'dl.Title as equipment_title',
                'dl.Code as equipment_code',
                'vi.Debit',
                'vi.Credit',
                DB::raw('(ISNULL(vi.Debit, 0) - ISNULL(vi.Credit, 0)) as amount')
            )
            ->orderBy('v.Date', 'desc')
            ->orderBy('v.Number', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * محاسبه مجموع هزینه‌ها
     */
    public function getTotalCost(
        string $equipmentCode,
        int $typeId,
        string $fromDate,
        string $toDate
    ): float {
        if (!$this->canAccessEquipmentType($typeId)) {
            abort(403, 'شما دسترسی به این نوع تجهیز را ندارید');
        }

        $equipmentCode = (string) $equipmentCode;

        return DB::connection('gtarabar')
            ->table('FIN3.Voucher as v')
            ->join('FIN3.VoucherItem as vi', 'v.VoucherID', '=', 'vi.VoucherRef')
            ->whereDate('v.Date', '>=', $fromDate)
            ->whereDate('v.Date', '<=', $toDate)
            ->where('vi.DLLevel5', '=', $equipmentCode)
            ->where('vi.DLTypeRef5', '=', $typeId)
            ->select(DB::raw('SUM(ISNULL(vi.Debit, 0) - ISNULL(vi.Credit, 0)) as total'))
            ->value('total') ?? 0.0;
    }
}
