<?php


namespace Modules\WarehouseGtrabar\App\Models\SqlServer;

use Illuminate\Database\Eloquent\Model;

class Part extends Model
{
    protected $connection = 'gtarabar';
    protected $table = 'LGS3.Part';
    public $timestamps = false;

    protected $fillable = [
        'PartID', 'Code', 'Name', 'LatinName', 'TechnicalSpecification',
        'State', 'Height', 'Weight', 'Length', 'Width', 'ProducerRef',
        'IsInputDocumentSuspended', 'IsOutputDocumentSuspended',
        'PropertiesComment', 'PartType', 'QuantityControlType',
        'CodeTemplateRef', 'CategoryRef', 'PartNature', 'MajorUnitRef',
        'MajorHomogeneousPartRef', 'CodeTemplateName', 'SecondType',
        'CreationDate', 'LastModificationDate', 'Creator', 'LastModifier',
        'ReservationLevel', 'ReserveWithTrackingFactor', 'IsTaxFree',
        'AdditionalField1', 'AdditionalField2', 'TaxId', 'TaxTitle'
    ];

    protected $casts = [
        'PartID' => 'integer',
        'State' => 'integer',
        'Height' => 'decimal:2',
        'Weight' => 'decimal:2',
        'Length' => 'decimal:2',
        'Width' => 'decimal:2',
        'ProducerRef' => 'integer',
        'IsInputDocumentSuspended' => 'boolean',
        'IsOutputDocumentSuspended' => 'boolean',
        'PartType' => 'integer',
        'QuantityControlType' => 'integer',
        'CodeTemplateRef' => 'integer',
        'CategoryRef' => 'integer',
        'PartNature' => 'integer',
        'MajorUnitRef' => 'integer',
        'MajorHomogeneousPartRef' => 'integer',
        'SecondType' => 'integer',
        'CreationDate' => 'datetime',
        'LastModificationDate' => 'datetime',
        'Creator' => 'integer',
        'LastModifier' => 'integer',
        'ReservationLevel' => 'integer',
        'ReserveWithTrackingFactor' => 'boolean',
        'IsTaxFree' => 'boolean',
    ];
}
