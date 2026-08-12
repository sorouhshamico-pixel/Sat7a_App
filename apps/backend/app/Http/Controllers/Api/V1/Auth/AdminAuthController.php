<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domain\Authentication\Actions\AdminLoginAction;
use App\Domain\Authentication\Exceptions\AdminAuthenticationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\AdminLoginRequest;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminAuthController extends Controller
{
    public function login(AdminLoginRequest $request, AdminLoginAction $action): JsonResponse
    {
        try {
            $result = $action->handle(
                email: $request->string('email')->toString(),
                password: $request->string('password')->toString(),
            );
        } catch (AdminAuthenticationException $e) {
            return ApiResponse::error($e->errorCode, $e->getMessage(), $e->status);
        }

        return ApiResponse::success($result);
    }

    /**
     * Revokes the caller's current token (customer/provider OTP session or
     * an admin's fully-privileged session). See docs/SECURITY.md §Sessions.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()->delete();

        return ApiResponse::success(['message' => 'Logged out.']);
    }
}
