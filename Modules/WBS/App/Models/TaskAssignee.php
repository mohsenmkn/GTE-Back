<?php


// Modules/WBS/Entities/TaskAssignee.php

namespace Modules\WBS\App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Modules\Auth\App\Models\User;

class TaskAssignee extends Pivot
{
    protected $table = 'task_assignees';

    protected $fillable = [
        'task_id',
        'user_id',
        'role',
    ];

    // Relations
    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
