<?php
namespace Modules\Assessment\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Auth\App\Models\User;

class AssessmentPeriod extends Model
{
    protected $table = 'assessment_periods';

    protected $fillable = [
        'title',
        'year',
        'start_date',
        'end_date',
        'status',
        'created_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    const STATUS_DRAFT  = 'draft';
    const STATUS_ACTIVE = 'active';
    const STATUS_CLOSED = 'closed';

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function targets(): HasMany
    {
        return $this->hasMany(AssessmentPeriodTarget::class, 'period_id');
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class, 'period_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeDraft($query)
    {
        return $query->where('status', self::STATUS_DRAFT);
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

    public function getTargetsCountAttribute(): int
    {
        return $this->targets()->count();
    }

    public function getAssessmentsCountAttribute(): int
    {
        return $this->assessments()->count();
    }
}
