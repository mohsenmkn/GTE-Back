<?php

namespace Modules\Dashboard\App\Http\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\Auth\App\Models\User;

class Announcement extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'type',
        'is_active',
        'published_at',
        'expires_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'published_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * رابطه با کاربر (برای اعلانات اختصاصی)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * کاربران این اعلان را خوانده‌اند
     */
    public function readers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'announcement_reads')
            ->withPivot('read_at')
            ->withTimestamps();
    }


    /**
     * Scope: فقط اعلانات فعال
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>=', now());
            });
    }

    /**
     * ✅ Scope جدید: اعلانات مربوط به یک کاربر خاص (سراسری + اختصاصی)
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->whereNull('user_id')           // اعلانات سراسری
            ->orWhere('user_id', $userId);    // اعلانات اختصاصی این کاربر
        });
    }
    /**
     * بررسی اینکه آیا کاربر این اعلان را خوانده است
     */
    public function isReadBy($user): bool
    {
        return $this->readers()->where('user_id', $user->id)->exists();
    }


}
