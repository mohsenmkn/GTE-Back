<?php

namespace Modules\VirtualSecretariat\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Auth\App\Models\User;

class VsRequest extends Model
{
    protected $table = 'vs_requests';

    protected $fillable = [
        'user_id',
        'template_id',
        'title',
        'request_data',
        'status',
        'automation_entity_code',
        'automation_send_code',
        'automation_letter_number',
        'admin_note',          // ✅ جدید
        'admin_user_id',       // ✅ جدید
        'error_message',
        'sent_at',
        'completed_at',
    ];

    protected $casts = [
        'request_data' => 'array',
        'sent_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(VsTemplate::class, 'template_id');
    }

    /**
     * ✅ اصلاح: مشخص کردن کلید خارجی به صورت صریح
     */
    public function workflowLogs(): HasMany
    {
        return $this->hasMany(VsWorkflowLog::class, 'request_id');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isSent(): bool
    {
        return $this->status === 'sent';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }
}
