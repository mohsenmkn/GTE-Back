<?php

namespace Modules\TripExpense\App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Auth\App\Models\User;

class ExpenseLimit extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'limit_type',
        'amount',
        'effective_from',
        'effective_to',
        'created_by',
        'updated_by',
        'is_active',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'is_active' => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'category_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopePerTrip($query)
    {
        return $query->where('limit_type', 'per_trip');
    }

    public function scopeMonthly($query)
    {
        return $query->where('limit_type', 'monthly');
    }

    public function scopeValidForDate($query, $date)
    {
        return $query->where('effective_from', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', $date);
            });
    }

    /*
    |--------------------------------------------------------------------------
    | Methods
    |--------------------------------------------------------------------------
    */

    public function isValidForDate($date): bool
    {
        return $this->effective_from <= $date
            && ($this->effective_to === null || $this->effective_to >= $date);
    }
}
