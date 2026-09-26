<?php

namespace Modules\HR\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Auth\App\Models\User;

class HRSyncLog extends Model
{
    // ✅ نام صریح جدول — Laravel دیگر حدس نمی‌زند
    protected $table = 'hr_sync_logs';
    protected $fillable = [
        'user_id',
        'sync_type',
        'status',
        'records_synced',
        'error_message',
        'duration_ms',
        'trigger_source',
    ];

    protected $casts = [
        'records_synced' => 'integer',
        'duration_ms'    => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ── Scopes ──

    public function scopeSuccess($query)
    {
        return $query->where('status', 'success');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }
}
