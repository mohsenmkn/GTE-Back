<?php
namespace Modules\Assessment\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentAction extends Model
{
    protected $table = 'assessment_actions';

    protected $fillable = [
        'gap_id',
        'method_id',
        'title',
        'description',
        'due_date',
        'status',
        'completed_at',
        'training_course_code',
    ];

    protected $casts = [
        'due_date' => 'date',
        'completed_at' => 'datetime',
    ];

    const STATUS_PLANNED   = 'planned';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';

    public function gap(): BelongsTo
    {
        return $this->belongsTo(AssessmentGap::class, 'gap_id');
    }

    public function method(): BelongsTo
    {
        return $this->belongsTo(AssessmentMethod::class, 'method_id');
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PLANNED     => 'برنامه‌ریزی شده',
            self::STATUS_IN_PROGRESS => 'در حال اجرا',
            self::STATUS_COMPLETED   => 'تکمیل شده',
            self::STATUS_CANCELLED   => 'لغو شده',
            default => $this->status,
        };
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->due_date && $this->due_date->isPast() && $this->status !== self::STATUS_COMPLETED;
    }
}
