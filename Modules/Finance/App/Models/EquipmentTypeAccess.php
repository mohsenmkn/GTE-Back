<?php

namespace Modules\Finance\App\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Auth\App\Models\User;

class EquipmentTypeAccess extends Model
{
    protected $table = 'equipment_type_accesses';

    protected $fillable = [
        'user_id',
        'role_id',
        'dl_type_ref',
        'dl_type_title',
        'can_view',
        'can_export',
    ];

    protected $casts = [
        'can_view'   => 'boolean',
        'can_export' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function role()
    {
        return $this->belongsTo(\Spatie\Permission\Models\Role::class, 'role_id');
    }
}
