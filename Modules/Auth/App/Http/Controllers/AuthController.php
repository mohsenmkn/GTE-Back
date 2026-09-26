<?php

namespace Modules\Auth\App\Http\Controllers;


use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use Modules\Auth\App\Models\User;
use Modules\Auth\App\Services\AuthService;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Validation\Rule;
use Spatie\Permission\PermissionRegistrar;
use Modules\HR\App\Jobs\SyncUserPositionJob;
use Modules\Auth\App\Models\LoginActivity;
class AuthController extends Controller
{
    protected $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    public function login(Request $request)
    {
        $request->validate([
            'mobile' => 'required|regex:/^09\d{9}$/',
            'password' => 'required',
            'captcha' => 'sometimes|nullable|string',
            'captcha_key' => 'sometimes|nullable|string',
        ]);

        $throttleKey = 'login_attempts:' . $request->mobile . '_' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            if (!$request->filled('captcha') || !$request->filled('captcha_key')) {
                return response()->json([
                    'message' => 'تعداد تلاش‌های ناموفق بیش از حد مجاز است. لطفا کد امنیتی را وارد کنید.',
                    'require_captcha' => true
                ], 429);
            }

            if (!captcha_api_check((string) $request->captcha, (string) $request->captcha_key)) {
                RateLimiter::hit($throttleKey, 300);
                return response()->json(['message' => 'کد امنیتی (کپچا) اشتباه است.'], 400);
            }
        }

        try {
            $result = $this->authService->login($request->only('mobile', 'password'));
            $user = $result['user'];

            if (!$user->canLogin()) {
                return response()->json([
                    'message' => $user->isContractor()
                        ? 'ورود برای این حساب امکان‌پذیر نیست'
                        : 'حساب کاربری شما غیرفعال است',
                ], 403);
            }

            // ✅ فقط وقتی ورود موفقیت‌آمیز بود، شمارنده پاک شود
            RateLimiter::clear($throttleKey);

            // ✅ ثبت LoginActivity
            LoginActivity::logLogin($user, $request);

            // ✅ قبل از return
            dispatch(new SyncUserPositionJob($user))->afterResponse();

            return response()->json([
                'token' => $result['token'],
                'user'  => $result['user']
            ]);
        } catch (ValidationException $e) {
            RateLimiter::hit($throttleKey, 300);
            return response()->json(['message' => $e->errors()['mobile'][0]], 401);
        }
    }

    public function sendOtp(Request $request)
    {
        $request->validate([
            'mobile' => 'required|regex:/^09\d{9}$/'
        ]);

        // جستجو یا ایجاد کاربر
        $result = $this->authService->findOrCreateUser($request->mobile);

        if (!$result['found']) {
            return response()->json([
                'message' => 'کاربری با این شماره موبایل در سیستم یافت نشد.',
                'can_register' => false
            ], 404);
        }

        /** @var User $user */
        $user = $result['user'];
        if (!$user->canLogin()) {
            return response()->json([
                'message' => $user->isContractor()
                    ? 'ورود برای این حساب امکان‌پذیر نیست'
                    : 'حساب کاربری شما غیرفعال است',
            ], 403);
        }

        try {
            $result = $this->authService->sendOtp($request->mobile);
            return response()->json($result, 200);
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->errors()['mobile'][0]], 400);
        } catch (\Exception $e) {
            Log::error('Send OTP Error: ' . $e->getMessage());
            return response()->json(['message' => 'خطا در ارسال کد تایید.'], 500);
        }
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'mobile' => 'required|exists:users,mobile',
            'otp' => 'required|string|size:5',
        ]);

        try {
            $user = $this->authService->verifyOtp($request->mobile, $request->otp);

            if (!$user->canLogin()) {
                return response()->json([
                    'message' => $user->isContractor()
                        ? 'ورود برای حساب‌های پیمانکار امکان‌پذیر نیست'
                        : 'حساب کاربری شما غیرفعال است',
                ], 403);
            }

            return response()->json([
                'message' => 'کد با موفقیت تایید شد.',
                'user' => [
                    'mobile' => $user->mobile,
                    'name' => $user->name
                ]
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => $e->errors()['otp'][0] ?? 'کد تایید نامعتبر است.'], 400);
        }
    }

    public function resetPassword(Request $request)
    {
        // ✅ اضافه کردن 'otp' به ولیدیشن برای جلوگیری از خطای null
        $request->validate([
            'mobile' => 'required|exists:users,mobile',
            'otp' => 'required|string|size:5', // <-- این خط حیاتی است
            'password' => 'required|string|min:6|confirmed',
        ]);

        try {
            // 1. ابتدا OTP را مجدداً بررسی می‌کنیم (امنیت)
            $user = $this->authService->verifyOtp($request->mobile, $request->otp);

            if (!$user->canLogin()) {
                return response()->json([
                    'message' => $user->isContractor()
                        ? 'ورود برای حساب‌های پیمانکار امکان‌پذیر نیست'
                        : 'حساب کاربری شما غیرفعال است',
                ], 403);
            }

            // 2. تغییر رمز عبور
            $this->authService->resetPassword($user, $request->password);

            return response()->json(['message' => 'رمز عبور با موفقیت تغییر کرد.'], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => $e->errors()['otp'][0] ?? 'کد تایید نامعتبر یا منقضی شده است.'], 400);
        } catch (\Exception $e) {
            Log::error('Reset Password Error: ' . $e->getMessage());
            return response()->json(['message' => 'خطا در تغییر رمز عبور. لطفاً مجدداً تلاش کنید.'], 500);
        }
    }

    public function logout(Request $request)
    {
        $token = $request->user()->currentAccessToken();

        if ($token) {
            $token->delete();
        }

        return response()->json(['message' => 'با موفقیت خارج شدید.']);
    }

    public function getCaptcha()
    {
        return response()->json(app('captcha')->create('default', true));
    }

    // ... (سایر متدهای me, getRoles, getPermissions و ... بدون تغییر باقی می‌مانند)


    public function me(Request $request)
    {
        $user = $request->user();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'mobile' => $user->mobile ?? null,
                'personnel_code'=> $user->personnel_code ?? null,
                'email' => $user->email ?? null,
                'roles' => $user->getRoleNames()->values(),
                'permissions' => $user->getAllPermissions()->pluck('name')->values(),
            ],
        ]);
    }
    /**
     * دریافت اطلاعات پروفایل کامل (با فعالیت‌ها و دستگاه‌ها)
     */
    public function profile(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        // گرفتن اطلاعات پایه
        $profile = $user->getDashboardProfile();

        // اضافه کردن فیلدهای اضافی
        $profile['email'] = $user->email;
        $profile['mobile'] = $user->mobile;
        $profile['national_code'] = $user->national_code;
        $profile['personnel_code'] = $user->personnel_code;
        $profile['login_count'] = LoginActivity::where('user_id', $user->id)->count();
        $profile['days_since_join'] = now()->diffInDays($user->created_at);

        // گرفتن نقش‌ها
        $roles = $user->getRoleNames()->values();

        // گرفتن فعالیت‌های اخیر
        $recentLogins = $user->getRecentLoginActivities(10);

        // گرفتن دستگاه‌های فعال
        $sessions = $user->getActiveSessions();

        return response()->json([
            'data' => $profile,
            'roles' => $roles,
            'recent_logins' => $recentLogins,
            'sessions' => $sessions,
        ]);
    }

    /**
     * بروزرسانی اطلاعات پروفایل (فقط فیلدهای مجاز)
     */
    public function updateProfile(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|max:255|unique:users,email,' . $user->id,
            'mobile' => 'sometimes|regex:/^09\d{9}$/|unique:users,mobile,' . $user->id,
            'national_code' => 'sometimes|digits:10|unique:users,national_code,' . $user->id,

        ]);

        $user->update($validated);

        return response()->json([
            'message' => 'پروفایل با موفقیت بروزرسانی شد',
            'data' => $user->fresh(),
        ]);
    }

    /**
     * تغییر رمز عبور
     */
    public function changePassword(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:6|confirmed',
        ]);

        if (!Hash::check($validated['current_password'], $user->password)) {
            return response()->json([
                'message' => 'رمز عبور فعلی اشتباه است'
            ], 422);
        }

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        return response()->json([
            'message' => 'رمز عبور با موفقیت تغییر کرد'
        ]);
    }

    /**
     * آپلود آواتار
     */
    public function uploadAvatar(Request $request)
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        /** @var User $user */
        $user = $request->user();

        if ($request->hasFile('avatar')) {
            // حذف آواتار قبلی
            if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }

            $path = $request->file('avatar')->store('avatars', 'public');
            $user->update(['avatar' => $path]);

            return response()->json([
                'message' => 'آواتار با موفقیت آپلود شد',
                'avatar' => Storage::disk('public')->url($path),
            ]);
        }

        return response()->json(['message' => 'خطا در آپلود'], 500);
    }

    /**
     * دریافت تنظیمات کاربر
     */
    public function getSettings(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        // تنظیمات را می‌توان در جدول settings یا JSON column در users ذخیره کرد
        // فعلاً از یک JSON column فرضی استفاده می‌کنیم
        $settings = $user->settings ?? [];

        return response()->json([
            'data' => [
                'general' => $settings['general'] ?? [
                        'language' => 'fa',
                        'timezone' => 'Asia/Tehran',
                        'darkMode' => false,
                        'animations' => true,
                        'density' => 'comfortable',
                    ],
                'notifications' => $settings['notifications'] ?? [
                        'email' => [
                            'system' => true,
                            'payslip' => true,
                            'library' => true,
                        ],
                        'push' => [
                            'enabled' => false,
                            'sound' => true,
                        ],
                    ],
            ],
            'sessions' => $user->getActiveSessions(),
        ]);
    }

    /**
     * بروزرسانی تنظیمات
     */
    public function updateSettings(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'general' => 'sometimes|array',
            'notifications' => 'sometimes|array',
        ]);

        $currentSettings = $user->settings ?? [];
        $newSettings = array_merge($currentSettings, $validated);

        $user->update(['settings' => $newSettings]);

        return response()->json([
            'message' => 'تنظیمات با موفقیت ذخیره شد',
            'data' => $newSettings,
        ]);
    }

    /**
     * دریافت لیست دستگاه‌های فعال
     */
    public function sessions(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'data' => $user->getActiveSessions(),
        ]);
    }

    /**
     * حذف یک نشست (دستگاه) خاص
     */
    /**
     * حذف یک نشست (دستگاه) خاص
     */
    public function revokeSession(Request $request, $activityId)
    {
        /** @var User $user */
        $user = $request->user();

        $activity = LoginActivity::where('user_id', $user->id)
            ->where('id', $activityId)
            ->first();

        if (!$activity) {
            return response()->json(['message' => 'نشست یافت نشد'], 404);
        }

        // جلوگیری از حذف نشست فعلی
        if ($activity->is_current) {
            return response()->json([
                'message' => 'نمی‌توانید نشست فعلی را حذف کنید'
            ], 422);
        }

        $activity->update(['is_current' => false]);

        return response()->json([
            'message' => 'نشست با موفقیت بسته شد'
        ]);
    }

    /**
     * خروج از تمام دستگاه‌ها
     */

    /**
     * خروج از تمام دستگاه‌ها
     */
    public function logoutAll(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        $currentActivityId = LoginActivity::where('user_id', $user->id)
            ->where('is_current', true)
            ->orderByDesc('login_at')
            ->value('id');

        $query = LoginActivity::where('user_id', $user->id)
            ->where('is_current', true);

        if ($currentActivityId) {
            $query->where('id', '!=', $currentActivityId);
        }

        $query->update(['is_current' => false]);

        return response()->json([
            'message' => 'از تمام دستگاه‌های دیگر خارج شدید'
        ]);
    }

    /**
     * دریافت لیست کاربران (برای Select در فرم‌ها)
     */
    public function getUsersList(Request $request)
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:100',
            'per_page' => 'nullable|integer|min:1|max:1000',
        ]);

        $search = $validated['search'] ?? '';
        $perPage = $validated['per_page'] ?? 50;

        $query = User::query()
            ->where('is_active', true)
            ->where('employee_type', '!=', 'contractor') // فقط پرسنل، نه پیمانکار
            ->orderBy('name');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('mobile', 'like', "%{$search}%")
                    ->orWhere('personnel_code', 'like', "%{$search}%");
            });
        }

        $users = $query->limit($perPage)->get()->map(fn ($user) => [
            'id' => $user->id,
            'name' => $user->name,
            'mobile' => $user->mobile,
            'personnel_code' => $user->personnel_code,
            'post_title' => $user->employeePosition?->post_title,
            'unit' => $user->employeePosition?->unit?->title,
        ]);

        return response()->json([
            'data' => $users,
        ]);
    }


}
