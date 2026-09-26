<?php

namespace Modules\PettyCash\App\Models;

// Modules/PettyCash/Entities/PettyCashTransaction.php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\WBS\App\Models\WbsItem;
use Modules\Document\App\Models\Document;
use Modules\Auth\App\Models\User;

class PettyCashTransaction extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'petty_cash_id',
        'wbs_item_id',
        'type',
        'amount',
        'transaction_date',
        'reference_number',
        'description',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'transaction_date' => 'date',
    ];

    // Relations
    public function pettyCash()
    {
        return $this->belongsTo(PettyCash::class);
    }

    public function wbsItem()
    {
        return $this->belongsTo(WbsItem::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function documents()
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    // Boot
    protected static function boot()
    {
        parent::boot();

        static::created(function ($transaction) {
            // ✅ فقط برای expense موجودی کم شود
            if ($transaction->type === 'expense') {
                $transaction->pettyCash->current_balance -= $transaction->amount;
            }
            // ✅ برای replenishment و adjustment موجودی زیاد شود
            else {
                $transaction->pettyCash->current_balance += $transaction->amount;
            }
            $transaction->pettyCash->save();
        });
    }
}
