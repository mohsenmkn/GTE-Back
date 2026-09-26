<?php

// Modules/Contract/App/Models/Contract.php

namespace Modules\Contract\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Project\App\Models\Project;
use Modules\Auth\App\Models\User;
use Modules\Company\App\Models\Company;
use Modules\WBS\App\Models\WbsItem;
use Modules\Document\App\Models\Document;

class Contract extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'project_id',
        'contractor_id',
        'contract_number',
        'title',
        'type',
        'amount',
        'paid_amount',
        'start_date',
        'end_date',
        'status',
        'description',
        'terms',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    // Relations
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }


    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function wbsItems()
    {
        return $this->belongsToMany(WbsItem::class, 'contract_wbs')
            ->withPivot('quantity', 'unit_price', 'total_price', 'description')
            ->withTimestamps();
    }

    public function documents()
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    // Helper Methods
    public function getRemainingAmountAttribute()
    {
        return $this->amount - $this->paid_amount;
    }

    public function getProgressPercentAttribute()
    {
        if ($this->amount == 0) return 0;
        return round(($this->paid_amount / $this->amount) * 100, 2);
    }

    public function isOverdue()
    {
        return $this->end_date && $this->end_date->isPast() && $this->status !== 'completed';
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeByProject($query, $projectId)
    {
        return $query->where('project_id', $projectId);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }
    public function contractor()
    {
        return $this->belongsTo(Contractor::class, 'contractor_id');
    }
    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    // Boot
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($contract) {
            if (empty($contract->contract_number)) {
                $contract->contract_number = 'CON-' . strtoupper(uniqid());
            }
        });
    }
}
