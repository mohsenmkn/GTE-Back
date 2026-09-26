<?php


namespace Modules\Auth\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoginActivity extends Model
{
    protected $table = 'login_activities';

    protected $fillable = [
        'user_id',
        'ip_address',
        'user_agent',
        'browser',
        'os',
        'device_type',
        'location',
        'is_current',
        'login_at',
    ];

    protected $casts = [
        'login_at' => 'datetime',
        'is_current' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * تشخیص نوع دستگاه از User Agent
     */
    public static function detectDeviceType(string $userAgent): string
    {
        if (preg_match('/Mobile|Android.*Mobile|iPhone|iPod|BlackBerry|IEMobile|Opera Mini/i', $userAgent)) {
            return 'mobile';
        }
        if (preg_match('/Tablet|iPad|Android(?!.*Mobile)/i', $userAgent)) {
            return 'tablet';
        }
        return 'desktop';
    }

    /**
     * تشخیص مرورگر از User Agent
     */
    public static function detectBrowser(string $userAgent): string
    {
        if (preg_match('/Edg\//i', $userAgent)) return 'Edge';
        if (preg_match('/OPR\//i', $userAgent)) return 'Opera';
        if (preg_match('/Chrome/i', $userAgent)) return 'Chrome';
        if (preg_match('/Safari/i', $userAgent)) return 'Safari';
        if (preg_match('/Firefox/i', $userAgent)) return 'Firefox';
        if (preg_match('/MSIE|Trident/i', $userAgent)) return 'IE';
        return 'Unknown';
    }

    /**
     * تشخیص سیستم عامل از User Agent
     */
    public static function detectOS(string $userAgent): string
    {
        if (preg_match('/Windows NT 10/i', $userAgent)) return 'Windows 10/11';
        if (preg_match('/Windows NT 6\.3/i', $userAgent)) return 'Windows 8.1';
        if (preg_match('/Windows NT 6\.1/i', $userAgent)) return 'Windows 7';
        if (preg_match('/Mac OS X/i', $userAgent)) return 'macOS';
        if (preg_match('/Android/i', $userAgent)) return 'Android';
        if (preg_match('/iPhone|iPad|iPod/i', $userAgent)) return 'iOS';
        if (preg_match('/Linux/i', $userAgent)) return 'Linux';
        return 'Unknown';
    }

    /**
     * ثبت یک لاگ جدید
     */
    /**
     * ثبت یک لاگ جدید
     */
    public static function logLogin($user, $request): self
    {
        // غیرفعال کردن همه لاگ‌های قبلی کاربر
        self::where('user_id', $user->id)
            ->where('is_current', true)
            ->update(['is_current' => false]);

        $userAgent = $request->userAgent() ?? '';

        return self::create([
            'user_id' => $user->id,
            'ip_address' => $request->ip(),
            'user_agent' => $userAgent,
            'browser' => self::detectBrowser($userAgent),
            'os' => self::detectOS($userAgent),
            'device_type' => self::detectDeviceType($userAgent),
            'location' => self::getLocationFromIp($request->ip()), // اختیاری
            'is_current' => true,
            'login_at' => now(),
        ]);
    }

    /**
     * تشخیص موقعیت جغرافیایی از IP (اختیاری)
     */
    private static function getLocationFromIp(string $ip): string
    {
        // می‌توانید از سرویس‌هایی مثل ip-api.com استفاده کنید
        // فعلاً یک مقدار پیش‌فرض برمی‌گردانیم
        return 'ایران';
    }
}
