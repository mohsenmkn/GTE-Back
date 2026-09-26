<?php
namespace Modules\Assessment\App\Models;

use Illuminate\Database\Eloquent\Model;

class AssessmentGeneralRisk extends Model
{
    protected $table = 'assessment_general_risks';

    protected $fillable = [
        'title',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
