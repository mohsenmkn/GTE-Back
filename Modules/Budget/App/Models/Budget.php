<?php

// Modules/Budget/Entities/Budget.php

namespace Modules\Budget\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Project\App\Models\Project;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Budget extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'title',
        'type',
        'amount',
        'used_amount',
        'fiscal_year',
        'description',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'used_amount' => 'decimal:2',
    ];

    protected $appends = ['remaining', 'usage_percent'];

    public function getRemainingAttribute()
    {
        return $this->amount - $this->used_amount;
    }

    public function getUsagePercentAttribute()
    {
        return $this->amount > 0 ? round(($this->used_amount / $this->amount) * 100, 2) : 0;
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}
