<?php
// Modules/Project/Entities/Project.php

namespace Modules\Project\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Auth\App\Models\User;
use Modules\Budget\App\Models\Budget;
use Modules\Company\App\Models\Company;
use Modules\Document\App\Models\Document;
use Modules\PettyCash\App\Models\PettyCash;
use Modules\WBS\App\Models\WbsItem;

class Project extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'type',
        'location',
        'start_date',
        'end_date',
        'total_budget',
        'status',
        'manager_id',
        'description',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'total_budget' => 'decimal:2',
    ];

    // Relations
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function wbsItems()
    {
        return $this->hasMany(WbsItem::class);
    }

    public function budgets()
    {
        return $this->hasMany(Budget::class);
    }

    public function pettyCash()
    {
        return $this->hasOne(PettyCash::class);
    }

    public function documents()
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }
}
