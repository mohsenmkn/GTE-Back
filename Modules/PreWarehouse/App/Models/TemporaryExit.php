<?php


namespace Modules\PreWarehouse\App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Auth\App\Models\User;

class TemporaryExit extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'pre_warehouse_temporary_exits';

    protected $fillable = [
        'allocation_id',
        'purchase_id',
        'quantity',
        'target_type',
        'target_code',
        'target_description',
        'site_name',
        'registered_by',
        'notes',
        'exit_date',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'exit_date' => 'datetime',
    ];

    // Relations
    public function allocation(): BelongsTo
    {
        return $this->belongsTo(Allocation::class);
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    // Helpers
    public function getTargetTypeLabelAttribute(): string
    {
        return [
            'equipment' => 'تجهیز',
            'vehicle' => 'وسیله نقلیه',
            'project' => 'پروژه',
            'other' => 'سایر',
        ][$this->target_type] ?? $this->target_type;
    }
}
