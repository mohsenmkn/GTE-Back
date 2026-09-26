<?php
namespace Modules\Assessment\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssessmentMethod extends Model
{
    protected $table = 'assessment_methods';

    protected $fillable = [
        'title',
        'is_active',
        'description',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function actions(): HasMany
    {
        return $this->hasMany(AssessmentAction::class, 'method_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
