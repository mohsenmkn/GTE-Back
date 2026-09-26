<?php


namespace Modules\WarehouseGtrabar\App\Models\SqlServer;

use Illuminate\Database\Eloquent\Model;

class InventoryVoucher extends Model
{
    protected $connection = 'gtarabar';
    protected $table = 'LGS3.InventoryVoucher';
    public $timestamps = false;

    protected $fillable = [
        'InventoryVoucherID', 'State', 'Number', 'Date', 'PlantRef',
        'StoreRef', 'CounterpartStoreRef', 'DelivererRef', 'ReferenceType',
        'ReferenceRef', 'CounterpartEntityCode', 'CounterpartEntityRef',
        'CounterpartEntityText', 'CounterpartDLCode', 'Level5DLCode',
        'Level6DLCode', 'LedgerRef', 'FiscalYearRef', 'CreationDate',
        'LastModificationDate', 'Creator', 'LastModifier'
    ];

    protected $casts = [
        'InventoryVoucherID' => 'integer',
        'State' => 'integer',
        'Date' => 'datetime',
        'PlantRef' => 'integer',
        'StoreRef' => 'integer',
        'CounterpartStoreRef' => 'integer',
        'DelivererRef' => 'integer',
        'ReferenceType' => 'integer',
        'ReferenceRef' => 'integer',
        'CounterpartEntityCode' => 'integer',
        'CounterpartEntityRef' => 'integer',
        'LedgerRef' => 'integer',
        'FiscalYearRef' => 'integer',
        'CreationDate' => 'datetime',
        'LastModificationDate' => 'datetime',
        'Creator' => 'integer',
        'LastModifier' => 'integer',
    ];
}
