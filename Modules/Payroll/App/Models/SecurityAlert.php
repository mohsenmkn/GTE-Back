<?php

namespace Modules\Payroll\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecurityAlert extends Model
{
    protected $table = 'security_alerts';

    protected $guarded = ['id'];

    protected $casts = [
        'occurrence_count' => 'integer',
        'first_detected_at' => 'datetime',
        'last_detected_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(\Modules\Auth\App\Models\User::class, 'resolved_by');
    }
}
