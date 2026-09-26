<?php


namespace Modules\WarehouseGtrabar\App\Models\SqlServer;

use Illuminate\Database\Eloquent\Model;

class InventoryVoucherSpecification extends Model
{
    protected $connection = 'gtarabar';
    protected $table = 'LGS3.InventoryVoucherSpecification';
    public $timestamps = false;

    protected $fillable = [
        'InventoryVoucherSpecificationID', 'Code', 'Name', 'Direction',
        'IsReturn', 'MaxRows', 'StockCaption', 'Category',
        'HasDelivererOrReceiver', 'DelivererOrReceiverCaption',
        'HasCounterpartStock', 'CounterpartStockCaption', 'State',
        'PartTypes', 'TypeOfEffectOnStock', 'ValidReferences',
        'DefaultReference', 'ReferencedVoucherSpecificationRef',
        'HasReference', 'Visible', 'Editable', 'IsWithoutReference',
        'PricingFactor', 'NeedsReturnableVoucherItem',
        'Additional1', 'Additional2', 'Additional3', 'Additional4',
        'Additional5', 'AdditionalItem1', 'AdditionalItem2',
        'AdditionalItem3', 'AdditionalItem4', 'AdditionalItem5',
        'TrackingFactorDialogState', 'ShowPartTechnicalSpecification',
        'ShowPartPropertiesComment', 'IsAdditional1Required',
        'IsAdditional2Required', 'IsAdditional3Required',
        'IsAdditional4Required', 'IsAdditional5Required',
        'IsAdditionalItem1Required', 'IsAdditionalItem2Required',
        'IsAdditionalItem3Required', 'IsAdditionalItem4Required',
        'IsAdditionalItem5Required', 'PurchaseType',
        'ShowPhysicalRemaining', 'ShowCommittableRemaining',
        'IsCorectiveVoucher', 'IsPriceDeviation'
    ];

    protected $casts = [
        'InventoryVoucherSpecificationID' => 'integer',
        'Direction' => 'integer',
        'IsReturn' => 'boolean',
        'MaxRows' => 'integer',
        'Category' => 'integer',
        'HasDelivererOrReceiver' => 'boolean',
        'HasCounterpartStock' => 'boolean',
        'State' => 'integer',
        'PartTypes' => 'integer',
        'TypeOfEffectOnStock' => 'integer',
        'ValidReferences' => 'integer',
        'DefaultReference' => 'integer',
        'ReferencedVoucherSpecificationRef' => 'integer',
        'HasReference' => 'boolean',
        'Visible' => 'boolean',
        'Editable' => 'boolean',
        'IsWithoutReference' => 'boolean',
        'PricingFactor' => 'integer',
        'NeedsReturnableVoucherItem' => 'boolean',
        'TrackingFactorDialogState' => 'integer',
        'ShowPartTechnicalSpecification' => 'boolean',
        'ShowPartPropertiesComment' => 'boolean',
        'IsAdditional1Required' => 'boolean',
        'IsAdditional2Required' => 'boolean',
        'IsAdditional3Required' => 'boolean',
        'IsAdditional4Required' => 'boolean',
        'IsAdditional5Required' => 'boolean',
        'IsAdditionalItem1Required' => 'boolean',
        'IsAdditionalItem2Required' => 'boolean',
        'IsAdditionalItem3Required' => 'boolean',
        'IsAdditionalItem4Required' => 'boolean',
        'IsAdditionalItem5Required' => 'boolean',
        'PurchaseType' => 'integer',
        'ShowPhysicalRemaining' => 'boolean',
        'ShowCommittableRemaining' => 'boolean',
        'IsCorectiveVoucher' => 'boolean',
        'IsPriceDeviation' => 'boolean',
    ];
}
