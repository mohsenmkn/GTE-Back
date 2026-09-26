<?php


namespace Modules\WarehouseGtrabar\App\Models\SqlServer;

use Illuminate\Database\Eloquent\Model;

class InventoryVoucherItem extends Model
{
    protected $connection = 'gtarabar';
    protected $table = 'LGS3.InventoryVoucherItem';
    public $timestamps = false;

    protected $fillable = [
        'InventoryVoucherItemID', 'InventoryVoucherRef', 'ReferenceType',
        'ReferenceRef', 'ReferenceCode', 'State', 'Description',
        'DelivererOrReceiverPartyRef', 'PartRef', 'PartUnitRef',
        'ReturnableVoucherItemRef', 'SLRef', 'CounterpartEntityCode',
        'CounterpartEntityRef', 'CounterpartEntityText',
        'CounterpartDLCode', 'Level5DLCode', 'Level6DLCode'
    ];

    protected $casts = [
        'InventoryVoucherItemID' => 'integer',
        'InventoryVoucherRef' => 'integer',
        'ReferenceType' => 'integer',
        'ReferenceRef' => 'integer',
        'State' => 'integer',
        'DelivererOrReceiverPartyRef' => 'integer',
        'PartRef' => 'integer',
        'PartUnitRef' => 'integer',
        'ReturnableVoucherItemRef' => 'integer',
        'SLRef' => 'integer',
        'CounterpartEntityCode' => 'integer',
        'CounterpartEntityRef' => 'integer',
    ];
}
