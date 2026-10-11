<?php

namespace Modules\TripExpense\App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Auth\App\Models\User;

class Truck extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'plate_number',
        'brand',
        'model',
        'status',
        'description',
    ];

    protected $casts = [
        'status' => 'string',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

    public function drivers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'driver_truck')
            ->withPivot(['start_date', 'end_date', 'note'])
            ->withTimestamps();
    }

    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class);
    }

    public function currentDrivers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'driver_truck')
            ->withPivot(['start_date', 'end_date', 'note'])
            ->withTimestamps()
            ->whereNull('driver_truck.end_date');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeInactive($query)
    {
        return $query->where('status', 'inactive');
    }
}
