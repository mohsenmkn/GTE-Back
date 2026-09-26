<?php
// Modules/WBS/Entities/Task.php

namespace Modules\WBS\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Auth\App\Models\User;
use Modules\WBS\App;

class Task extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'wbs_item_id',
        'title',
        'description',
        'assigned_to',
        'start_date',
        'due_date',
        'completed_at',
        'priority',
        'status',
        'progress_percent',
        'estimated_hours',
        'actual_hours',
        'notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'due_date' => 'date',
        'completed_at' => 'datetime',
        'progress_percent' => 'decimal:2',
    ];

    // Relations
    public function wbsItem()
    {
        return $this->belongsTo(WbsItem::class);
    }

    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function assignees()
    {
        return $this->belongsToMany(User::class, 'task_assignees')
            ->withPivot('role')
            ->withTimestamps();
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeOverdue($query)
    {
        return $query->where('due_date', '<', now())
            ->whereNotIn('status', ['completed']);
    }

    // Helper methods
    public function isOverdue()
    {
        return $this->due_date &&
            $this->due_date->isPast() &&
            $this->status !== 'completed';
    }

    public function markAsCompleted()
    {
        $this->update([
            'status' => 'completed',
            'progress_percent' => 100,
            'completed_at' => now(),
        ]);
    }
}
