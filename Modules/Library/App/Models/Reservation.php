<?php

namespace Modules\Library\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;
use Modules\Auth\App\Models\User;

class Reservation extends Model
{
    protected $table = 'library_reservations';

    protected $fillable = [
        'user_id',
        'book_copy_id',
        'reservation_date',
        'expected_pickup_date',
        'actual_pickup_date',
        'expected_return_date',
        'actual_return_date',
        'status',
        'notes',
    ];

    protected $casts = [
        'reservation_date' => 'datetime',
        'expected_pickup_date' => 'date',
        'actual_pickup_date' => 'datetime',
        'expected_return_date' => 'date',
        'actual_return_date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function bookCopy(): BelongsTo
    {
        return $this->belongsTo(BookCopy::class, 'book_copy_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'reservation_id');
    }

    // آیا رزرو فعال است؟
    public function isActive(): bool
    {
        return in_array($this->status, ['pending', 'approved', 'picked_up']);
    }

    // آیا از سررسید گذشته است؟
    public function isOverdue(): bool
    {
        if ($this->status !== 'picked_up') {
            return false;
        }

        return Carbon::now()->gt($this->expected_return_date);
    }

    // تعداد روزهای باقی‌مانده تا سررسید
    public function daysUntilDue(): int
    {
        if ($this->status !== 'picked_up') {
            return 0;
        }

        return Carbon::now()->diffInDays($this->expected_return_date, false);
    }
}
