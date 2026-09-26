<?php


namespace Modules\WarehouseGtrabar\App\Models\SqlServer;

use Illuminate\Database\Eloquent\Model;

class Store extends Model
{
    protected $connection = 'gtarabar';
    protected $table = 'LGS3.Store';
    public $timestamps = false;

    protected $fillable = [
        'StoreID', 'Name', 'Code', 'PartyRef', 'State', 'PlantRef',
        'IntermediateGroupMembership', 'IsDisjoint', 'NoneCountable',
        'BranchRef', 'GLN'
    ];

    protected $casts = [
        'StoreID' => 'integer',
        'PartyRef' => 'integer',
        'State' => 'integer',
        'PlantRef' => 'integer',
        'IntermediateGroupMembership' => 'boolean',
        'IsDisjoint' => 'boolean',
        'NoneCountable' => 'integer',
        'BranchRef' => 'integer',
    ];
}
