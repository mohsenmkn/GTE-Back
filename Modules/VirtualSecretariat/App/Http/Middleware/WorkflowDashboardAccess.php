<?php


namespace Modules\VirtualSecretariat\App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class WorkflowDashboardAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'لطفاً وارد شوید'
            ], 401);
        }

        // ✅ بررسی ساده: فقط ادمین یا کاربر با نقش خاص
        $roles = $user->getRoleNames()->toArray();

        $hasAccess = in_array('admin', $roles)
            || in_array('super-admin', $roles)
            || in_array('unit_manager', $roles);

        if (!$hasAccess) {
            return response()->json([
                'success' => false,
                'message' => 'دسترسی غیرمجاز - فقط مدیران مجاز به مشاهده این بخش هستند'
            ], 403);
        }

        return $next($request);
    }
}
