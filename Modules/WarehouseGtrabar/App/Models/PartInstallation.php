<?php


namespace Modules\WarehouseGtrabar\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartInstallation extends Model
{
    protected $table = 'part_installations';

    protected $fillable = [
        'equipment_id',
        'part_code',
        'part_name',
        'part_sql_server_id',
        'installed_at',
        'removed_at',
        'installation_location',
        'serial_number',
        'notes',
        'installed_by',
        'removed_by',
    ];

    protected $casts = [
        'equipment_id' => 'integer',
        'part_sql_server_id' => 'integer',
        'installed_at' => 'datetime',
        'removed_at' => 'datetime',
        'installed_by' => 'integer',
        'removed_by' => 'integer',
    ];

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class, 'equipment_id');
    }

    public function installer(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'installed_by');
    }

    public function remover(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'removed_by');
    }

    public function scopeActive($query)
    {
        return $query->whereNull('removed_at');
    }

    public function scopeInactive($query)
    {
        return $query->whereNotNull('removed_at');
    }
}
