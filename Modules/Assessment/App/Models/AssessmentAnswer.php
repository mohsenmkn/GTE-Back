<?php
namespace Modules\Assessment\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentAnswer extends Model
{
    protected $table = 'assessment_answers';

    protected $fillable = [
        'assessment_id',
        'question_id',
        'score',
        'comment',
    ];

    protected $casts = [
        'score' => 'integer',
    ];

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(AssessmentQuestion::class, 'question_id');
    }

    /**
     * گپ این پاسخ نسبت به نمره مطلوب
     */
    public function getGapAttribute(): int
    {
        $required = $this->question?->effective_required_score ?? 5;
        return max(0, $required - ($this->score ?? 0));
    }

    
}
