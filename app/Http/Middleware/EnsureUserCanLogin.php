<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserCanLogin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'احراز هویت انجام نشده است',
            ], 401);
        }

        if (!$user->canLogin()) {
            /**
             * اگر توکن فعلی وجود داشت، حذف شود.
             */
            if (method_exists($user, 'currentAccessToken')) {
                $token = $user->currentAccessToken();

                if ($token) {
                    $token->delete();
                }
            }

            return response()->json([
                'message' => $user->isContractor()
                    ? 'دسترسی برای حساب‌های پیمانکار امکان‌پذیر نیست'
                    : 'حساب کاربری شما غیرفعال است',
            ], 403);
        }

        return $next($request);
    }
}
