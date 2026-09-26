<?php
// Modules/Company/Entities/Company.php

namespace Modules\Company\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Project\App\Models\Project;

class Company extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'registration_number',
        'tax_number',
        'address',
        'phone',
        'email',
        'website',
        'logo',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // Relations
    public function projects()
    {
        return $this->hasMany(Project::class);
    }
}
