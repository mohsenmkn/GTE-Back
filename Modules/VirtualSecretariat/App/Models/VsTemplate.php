<?php

namespace Modules\VirtualSecretariat\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class VsTemplate extends Model
{
    use SoftDeletes;

    protected $table = 'vs_templates';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'db_connection_name',
        'entity_mapping',
        'workflow_mapping',
        'letter_number_format',
        'entity_type_code',
        'virtual_personnel_user_id',
        'virtual_personnel_role_id',
        'receiver_role_id',
        'receiver_user_id', // ✅ جدید
        'target_action_code',
        'is_active',
    ];

    protected $casts = [
        'entity_mapping' => 'array',
        'workflow_mapping' => 'array',
        'letter_number_format' => 'array',
        'entity_type_code' => 'integer',
        'receiver_user_id' => 'integer', // ✅ جدید
        'is_active' => 'boolean',
    ];

    public function requests()
    {
        return $this->hasMany(VsRequest::class, 'template_id');
    }

    public function letterCounters()
    {
        return $this->hasMany(VsLetterCounter::class);
    }

    public function isActive(): bool
    {
        return $this->is_active === true;
    }
}
