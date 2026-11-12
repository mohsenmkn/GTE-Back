<?php

namespace Modules\Auth\App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

class AuthFailureLimiter
{
    private const MAX_FAILURES = 10;
    private const WINDOW_SECONDS = 300;

    public function isLocked(Request $request, ?string $mobile): bool
    {
        return Cache::has($this->lockKey($request, $mobile));
    }

    public function secondsUntilUnlock(Request $request, ?string $mobile): int
    {
        $lockedUntil = Cache::get($this->lockKey($request, $mobile));

        return max(0, (int) $lockedUntil - now()->timestamp);
    }

    public function recordFailure(Request $request, ?string $mobile): bool
    {
        $key = $this->counterKey($request, $mobile);
        RateLimiter::hit($key, self::WINDOW_SECONDS);

        if (RateLimiter::tooManyAttempts($key, self::MAX_FAILURES)) {
            Cache::put(
                $this->lockKey($request, $mobile),
                now()->addSeconds(self::WINDOW_SECONDS)->timestamp,
                now()->addSeconds(self::WINDOW_SECONDS),
            );

            return true;
        }

        return false;
    }

    public function clear(Request $request, ?string $mobile): void
    {
        RateLimiter::clear($this->counterKey($request, $mobile));
        Cache::forget($this->lockKey($request, $mobile));
    }

    private function counterKey(Request $request, ?string $mobile): string
    {
        return 'auth-failures:'.$this->fingerprint($request, $mobile);
    }

    private function lockKey(Request $request, ?string $mobile): string
    {
        return 'auth-lock:'.$this->fingerprint($request, $mobile);
    }

    private function fingerprint(Request $request, ?string $mobile): string
    {
        $userAgent = substr((string) $request->userAgent(), 0, 512);
        $clientHints = substr((string) $request->header('Sec-CH-UA'), 0, 256);
        $normalizedMobile = preg_replace('/\D+/', '', (string) $mobile);

        return hash_hmac(
            'sha256',
            implode("\0", [$request->ip(), $userAgent, $clientHints, $normalizedMobile]),
            (string) config('app.key'),
        );
    }
}
