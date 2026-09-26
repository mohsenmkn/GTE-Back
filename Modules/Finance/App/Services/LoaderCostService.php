<?php
// Modules/Finance/Services/LoaderCostService.php
namespace Modules\Finance\Services;

use Illuminate\Support\Facades\DB;

class LoaderCostService
{
    public function getLoaderCosts(string $loaderCode, string $fromDate, string $toDate): array
    {
        return DB::connection('gtarabar') // یا نام connection شما
        ->table('FIN3.Voucher as v')
            ->join('FIN3.VoucherItem as vi', 'v.VoucherID', '=', 'vi.VoucherRef')
            ->join('FIN3.SL as sl', 'vi.SLRef', '=', 'sl.SLID')
            ->join('FIN3.DL as dl', function($join) {
                $join->on('dl.Code', '=', 'vi.DLLevel5')
                    ->where('dl.DLTypeRef', '=', 5); // تنظیم بر اساس کوئری ۹
            })
            ->where('v.State', 2)
            ->whereBetween('v.Date', [$fromDate, $toDate])
            ->where('vi.DLLevel5', $loaderCode)
            ->whereIn('sl.Nature', [2, 6]) // ماهیت هزینه
            ->select(
                'v.VoucherID',
                'v.Number as voucher_number',
                'v.Date as voucher_date',
                'sl.Title as account_title',
                'dl.Title as detail_title',
                'vi.Debit',
                'vi.Credit',
                DB::raw('(ISNULL(vi.Debit, 0) - ISNULL(vi.Credit, 0)) as amount')
            )
            ->orderBy('v.Date', 'desc')
            ->get()
            ->toArray();
    }
}
