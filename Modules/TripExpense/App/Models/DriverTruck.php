<?php

namespace Modules\TripExpense\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Auth\App\Models\User;

class DriverTruck extends Model
{
    protected $table = 'driver_truck';

    protected $fillable = [
        'user_id',
        'truck_id',
        'start_date',
        'end_date',
        'note',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function truck(): BelongsTo
    {
        return $this->belongsTo(Truck::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeActive($query)
    {
        return $query->whereNull('end_date');
    }

    public function scopeEnded($query)
    {
        return $query->whereNotNull('end_date');
    }

    public function scopeForDriver($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForTruck($query, $truckId)
    {
        return $query->where('truck_id', $truckId);
    }

    /*
    |--------------------------------------------------------------------------
    | Methods
    |--------------------------------------------------------------------------
    */

    public function isActive(): bool
    {
        return is_null($this->end_date);
    }
}
