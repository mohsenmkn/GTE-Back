<?php

// Modules/Contract/App/Models/ContractWbs.php

namespace Modules\Contract\App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Modules\WBS\App\Models\WbsItem;

class ContractWbs extends Pivot
{
    protected $table = 'contract_wbs';

    protected $fillable = [
        'contract_id',
        'wbs_item_id',
        'quantity',
        'unit_price',
        'total_price',
        'description',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
    ];

    public function contract()
    {
        return $this->belongsTo(Contract::class);
    }

    public function wbsItem()
    {
        return $this->belongsTo(WbsItem::class);
    }
}
