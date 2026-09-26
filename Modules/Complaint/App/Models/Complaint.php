<?php

namespace Modules\Complaint\App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Auth\App\Models\User;
use Modules\Complaint\App\Enums\ComplaintPriority;
use Modules\Complaint\App\Enums\ComplaintStatus;
use Modules\HR\App\Models\OrganizationalUnit;
use Morilog\Jalali\Jalalian;

class Complaint extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'complaint_category_id',
        'tracking_code',
        'organizational_unit_id',  // ✅ جدید
        'assigned_to',              // ✅ جدید
        'assigned_at',              // ✅ جدید
        'subject',
        'description',
        'status',
        'priority',
        'jalali_date',
        'answered_at',
        'resolved_at',

    ];

    protected $casts = [
        'status' => ComplaintStatus::class,
        'priority' => ComplaintPriority::class,
        'answered_at' => 'datetime',
        'resolved_at' => 'datetime',
        'assigned_at' => 'datetime',  // ✅ جدید
    ];

    protected static function booted(): void
    {
        static::creating(function (Complaint $complaint) {
            if (empty($complaint->jalali_date)) {
                $complaint->jalali_date = Jalalian::now()->format('Y-m-d');
            }

            if (empty($complaint->tracking_code)) {
                $complaint->tracking_code = self::generateTrackingCode($complaint->jalali_date);
            }

            if (empty($complaint->status)) {
                $complaint->status = ComplaintStatus::Pending;
            }

            if (empty($complaint->priority)) {
                $complaint->priority = ComplaintPriority::Medium;
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ComplaintCategory::class, 'complaint_category_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(ComplaintReply::class)->latest();
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ComplaintAttachment::class);
    }

    public function smsLogs(): HasMany
    {
        return $this->hasMany(ComplaintSmsLog::class);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    protected static function generateTrackingCode(string $jalaliDate): string
    {
        $date = str_replace('-', '', $jalaliDate);
        $prefix = 'CMP-' . $date . '-';

        $last = static::withTrashed()
            ->where('jalali_date', $jalaliDate)
            ->orderByDesc('id')
            ->first();

        $sequence = 1;

        if ($last && preg_match('/(\d{3})$/', $last->tracking_code, $matches)) {
            $sequence = ((int) $matches[1]) + 1;
        }

        do {
            $code = $prefix . str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
            $sequence++;
        } while (static::withTrashed()->where('tracking_code', $code)->exists());

        return $code;
    }


    /**
     * Scope برای اعمال فیلترهای لیست شکایات
     * در index و export استفاده می‌شود
     */
    public function scopeFilter($query, array $filters)
    {
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        if (!empty($filters['category_id'])) {
            $query->where('complaint_category_id', $filters['category_id']);
        }

        if (!empty($filters['date_from'])) {
            $query->where('jalali_date', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->where('jalali_date', '<=', $filters['date_to']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('tracking_code', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('mobile', 'like', "%{$search}%")
                            ->orWhere('personnel_code', 'like', "%{$search}%");
                    });
            });
        }

        return $query;
    }

    // ✅ relationship های جدید
    public function organizationalUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationalUnit::class);
    }

    // ✅ scope جدید (اختیاری - برای استفاده در جاهای دیگر)
    public function scopeAssignedTo($query, int $userId)
    {
        return $query->where('assigned_to', $userId);
    }

    /**
     * رابطه با مسئول پیگیری
     */
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }



}
