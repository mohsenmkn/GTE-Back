<?php
namespace Modules\Assessment\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssessmentCycle extends Model
{
    protected $table = 'assessment_cycles';

    protected $fillable = [
        'title',
        'type',
        'start_date',
        'end_date',
        'status',
        'description',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    const STATUS_DRAFT  = 'draft';
    const STATUS_ACTIVE = 'active';
    const STATUS_CLOSED = 'closed';

    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class, 'cycle_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT  => 'پیش‌نویس',
            self::STATUS_ACTIVE => 'فعال',
            self::STATUS_CLOSED => 'بسته شده',
            default => $this->status,
        };
    }

    public function getAssessmentsCountAttribute(): int
    {
        return $this->assessments()->count();
    }
}
