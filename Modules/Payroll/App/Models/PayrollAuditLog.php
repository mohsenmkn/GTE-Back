<?php

namespace Modules\Payroll\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollAuditLog extends Model
{
    protected $table = 'payroll_audit_logs';

    public $timestamps = false;

    protected $guarded = ['id', 'created_at'];

    protected $casts = [
        'created_at' => 'datetime',
        'year_month' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(\Modules\Auth\App\Models\User::class);
    }
}
