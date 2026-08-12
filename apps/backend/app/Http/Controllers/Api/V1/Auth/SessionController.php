<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Support\Enums\ErrorCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Lets a user see and manage their own active sessions (Sanctum tokens —
 * one per device/login). See docs/SECURITY.md §Sessions. A user can only
 * ever see/revoke their own tokens; there is no cross-user lookup here.
 */
class SessionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $currentTokenId = $request->user()->currentAccessToken()->id;

        $sessions = $request->user()->tokens()
            ->orderByDesc('last_used_at')
            ->get()
            ->map(fn ($token) => [
                'id' => $token->id,
                'name' => $token->name,
                'abilities' => $token->abilities,
                'last_used_at' => $token->last_used_at,
                'created_at' => $token->created_at,
                'is_current' => $token->id === $currentTokenId,
            ]);

        return ApiResponse::success(['sessions' => $sessions]);
    }

    public function destroy(Request $request, int $tokenId): JsonResponse
    {
        $deleted = $request->user()->tokens()->where('id', $tokenId)->delete();

        if ($deleted === 0) {
            return ApiResponse::error(ErrorCode::NotFound, 'Session not found.', 404);
        }

        return ApiResponse::success(['message' => 'Session revoked.']);
    }

    public function destroyAll(Request $request): JsonResponse
    {
        $currentTokenId = $request->user()->currentAccessToken()->id;

        $request->user()->tokens()->where('id', '!=', $currentTokenId)->delete();

        return ApiResponse::success(['message' => 'All other sessions revoked.']);
    }
}
