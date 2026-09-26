<?php


namespace Modules\WarehouseGtrabar\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Equipment extends Model
{
    protected $table = 'equipment';

    protected $fillable = [
        'code',
        'title',
        'title_en',
        'type',
        'description',
        'state',
        'parent_equipment_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'state' => 'integer',
        'type' => 'integer',
        'parent_equipment_id' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];

    public function parent(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Equipment::class, 'parent_equipment_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Equipment::class, 'parent_equipment_id');
    }

    public function installations(): HasMany
    {
        return $this->hasMany(PartInstallation::class, 'equipment_id');
    }

    public function currentInstallations(): HasMany
    {
        return $this->hasMany(PartInstallation::class, 'equipment_id')
            ->whereNull('removed_at');
    }
}
