<?php


namespace Modules\Finance\App\Models;

use Illuminate\Database\Eloquent\Model;

class VoucherItem extends Model
{
    protected $connection = 'gtarabar';

    protected $table = 'FIN3.VoucherItem';
    protected $primaryKey = 'VoucherItemID';

    protected $fillable = [
        'VoucherRef', 'SLRef', 'Debit', 'Credit',
        'DLLevel4', 'DLTypeRef4', 'DLLevel5', 'DLTypeRef5'
    ];

    public $timestamps = false;

    public function voucher()
    {
        return $this->belongsTo(Voucher::class, 'VoucherRef', 'VoucherID');
    }

    public function sl()
    {
        return $this->belongsTo(SL::class, 'SLRef', 'SLID');
    }

    public function detail()
    {
        return $this->belongsTo(DL::class, 'DLLevel5', 'Code')
            ->where('DLTypeRef', $this->DLTypeRef5);
    }
}
