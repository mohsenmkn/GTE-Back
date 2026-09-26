<?php

namespace Modules\VirtualSecretariat\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VsWorkflowLog extends Model
{
    protected $table = 'vs_workflow_logs';

    protected $fillable = [
        'request_id',
        'automation_receiver_code',
        'receiver_role_id',
        'action_code',
        'action_name',
        'state',
        'receive_date',
        'response_date',
        'response_text',
    ];

    protected $casts = [
        'receive_date' => 'datetime',
        'response_date' => 'datetime',
    ];

    /**
     * ✅ اصلاح: مشخص کردن کلید خارجی به صورت صریح
     */
    public function request(): BelongsTo
    {
        return $this->belongsTo(VsRequest::class, 'request_id');
    }

    public function isWaiting(): bool
    {
        return $this->state === 'waiting';
    }

    public function isFinished(): bool
    {
        return $this->state === 'finished';
    }
}
