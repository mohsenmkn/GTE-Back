<?php

namespace Modules\ApiManagement\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ApiClientController extends Controller
{
    private const PREFIX = 'api-management:';

    public function index(Request $request): JsonResponse
    {
        $tokens = $request->user()->tokens()
            ->where('name', 'like', self::PREFIX . '%')
            ->orderByDesc('created_at')
            ->get(['id', 'name', 'abilities', 'last_used_at', 'expires_at', 'created_at']);

        return response()->json(['clients' => $tokens]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'regex:/^[\p{L}\p{M}\p{N} ._\-\x{200c}]+$/u'],
            'abilities' => ['required', 'array', 'min:1'],
            'abilities.*' => ['required', 'string', Rule::in(['hr:employees:read', 'hr:hierarchy:read', 'hr:attendance:read'])],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);

        $abilities = array_values(array_unique($data['abilities']));
        $token = $request->user()->createToken(
            self::PREFIX . $data['name'],
            $abilities,
            isset($data['expires_at']) ? Carbon::parse($data['expires_at']) : null
        );

        return response()->json([
            'client' => [
                'id' => $token->accessToken->id,
                'name' => $token->accessToken->name,
                'abilities' => $token->accessToken->abilities,
                'expires_at' => $token->accessToken->expires_at,
            ],
            'token' => $token->plainTextToken,
            'message' => 'این توکن فقط همین بار نمایش داده می‌شود؛ آن را در محل امن نگهداری کنید.',
        ], 201);
    }

    public function destroy(Request $request, int $token): JsonResponse
    {
        $deleted = $request->user()->tokens()
            ->whereKey($token)
            ->where('name', 'like', self::PREFIX . '%')
            ->delete();

        abort_unless($deleted, 404);

        return response()->json(['message' => 'دسترسی API لغو شد.']);
    }
}
