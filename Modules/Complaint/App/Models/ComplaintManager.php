<?php
// Modules/Complaint/App/Models/ComplaintManager.php

namespace Modules\Complaint\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Auth\App\Models\User;
use Modules\HR\App\Models\OrganizationalUnit;

class ComplaintManager extends Model
{
    protected $fillable = [
        'organizational_unit_id',
        'user_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function organizationalUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationalUnit::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForUnit($query, int $unitId)
    {
        return $query->where('organizational_unit_id', $unitId);
    }
}
