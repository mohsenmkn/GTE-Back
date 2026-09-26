<?php

namespace Modules\PreWarehouse\App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Auth\App\Models\User;

class Approval extends Model
{
    use HasFactory;
    protected $table = 'pre_warehouse_approvals';
    protected $fillable = [
        'purchase_id', 'approver_type', 'approver_id', 'status',
        'approved_at', 'approval_notes', 'rejection_reason', 'rejected_at',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function purchase(): BelongsTo { return $this->belongsTo(Purchase::class); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approver_id'); }

    public function isApproved(): bool { return $this->status === 'approved'; }
    public function isRejected(): bool { return $this->status === 'rejected'; }
    public function isPending(): bool { return $this->status === 'pending'; }

    public function getStatusLabelAttribute(): string
    {
        return [
            'pending' => 'در انتظار',
            'approved' => 'تأیید شده',
            'rejected' => 'رد شده',
        ][$this->status] ?? $this->status;
    }
}
