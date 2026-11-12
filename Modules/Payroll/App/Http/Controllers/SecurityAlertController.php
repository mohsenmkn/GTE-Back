<?php

namespace Modules\Payroll\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Payroll\App\Models\SecurityAlert;
use Modules\Payroll\App\Services\SystemAuditLogger;

class SecurityAlertController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', 'in:open,resolved'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
        ]);

        $alerts = SecurityAlert::query()
            ->with('resolver:id,name')
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->latest('last_detected_at')
            ->paginate($filters['per_page'] ?? 20);

        return response()->json($alerts);
    }

    public function resolve(Request $request, SecurityAlert $alert): JsonResponse
    {
        if ($alert->status !== 'resolved') {
            $alert->forceFill([
                'status' => 'resolved',
                'resolved_at' => now(),
                'resolved_by' => $request->user()?->id,
            ])->save();

            app(SystemAuditLogger::class)->record(
                $request,
                'Security',
                'alert.resolved',
                'success',
                null,
                'alert:'.$alert->id,
            );
        }

        return response()->json(['data' => $alert->fresh()->load('resolver:id,name')]);
    }
}
