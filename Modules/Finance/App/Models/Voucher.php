<?php


namespace Modules\Finance\App\Models;

use Illuminate\Database\Eloquent\Model;

class Voucher extends Model
{
    protected $connection = 'gtarabar'; // ← این خط اضافه شود

    protected $table = 'FIN3.Voucher';
    protected $primaryKey = 'VoucherID';

    protected $fillable = [
        'Number', 'Date', 'Description', 'State', 'VoucherTypeRef',
        'LedgerRef', 'FiscalYearRef', 'BranchRef'
    ];

    public $timestamps = false;

    public function items()
    {
        return $this->hasMany(VoucherItem::class, 'VoucherRef', 'VoucherID');
    }
}
