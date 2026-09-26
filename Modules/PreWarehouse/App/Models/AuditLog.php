<?php

namespace Modules\PreWarehouse\App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Modules\Auth\App\Models\User;

class AuditLog extends Model
{
    use HasFactory;
    protected $table = 'pre_warehouse_audit_logs';

    protected $fillable = [
        'auditable_type', 'auditable_id', 'user_id', 'action',
        'from_status', 'to_status', 'notes', 'changes',
    ];

    protected $casts = [
        'changes' => 'array',
    ];

    public function auditable(): MorphTo { return $this->morphTo(); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
