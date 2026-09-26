<?php
// Modules/Inventory/App/Models/WarehouseTransaction.php

namespace Modules\Warehouse\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Project\App\Models\Project;
use Modules\WBS\App\Models\WbsItem;
use Modules\Auth\App\Models\User;
use Modules\Contract\App\Models\Contract;

class WarehouseTransaction extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'material_id',
        'project_id',
        'wbs_item_id',
        'contract_id',
        'type',
        'quantity',
        'unit_price',
        'total_price',
        'transaction_date',
        'reference_number',
        'description',
        'created_by',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'transaction_date' => 'date',
    ];

    // Relations
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function material()
    {
        return $this->belongsTo(Material::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function wbsItem()
    {
        return $this->belongsTo(WbsItem::class);
    }

    public function contract()
    {
        return $this->belongsTo(Contract::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Boot
    protected static function boot()
    {
        parent::boot();

        static::created(function ($transaction) {
            $transaction->material->updateStock($transaction->quantity, $transaction->type);
        });

        static::deleted(function ($transaction) {
            // برگرداندن موجودی هنگام حذف تراکنش
            $reverseType = [
                'purchase' => 'sale',
                'sale' => 'purchase',
                'transfer' => 'return',
                'return' => 'sale',
                'adjustment' => 'adjustment',
                'damage' => 'return',
            ];
            $transaction->material->updateStock($transaction->quantity, $reverseType[$transaction->type] ?? 'sale');
        });
    }

    // Accessors
    public function getTypeLabelAttribute()
    {
        $labels = [
            'purchase' => 'خرید',
            'sale' => 'فروش',
            'transfer' => 'انتقال',
            'adjustment' => 'تعدیل',
            'return' => 'بازگشت',
            'damage' => 'ضایعات',
        ];
        return $labels[$this->type] ?? $this->type;
    }
}
