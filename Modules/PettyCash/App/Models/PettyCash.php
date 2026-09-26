<?php

// Modules/PettyCash/App/Models/PettyCash.php

namespace Modules\PettyCash\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Project\App\Models\Project;
use Modules\Auth\App\Models\User;

class PettyCash extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'project_id',
        'name',
        'initial_amount',
        'current_balance',
        'is_active',
    ];

    protected $casts = [
        'initial_amount' => 'decimal:2',
        'current_balance' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    // Relations
    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function transactions()
    {
        return $this->hasMany(PettyCashTransaction::class);
    }

    // ✅ Helper methods
    public function addTransaction($amount, $type)
    {
        if ($type === 'expense') {
            $this->current_balance -= $amount;
        } else {
            $this->current_balance += $amount;
        }
        $this->save();
    }

    // ✅ Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByProject($query, $projectId)
    {
        return $query->where('project_id', $projectId);
    }
}
