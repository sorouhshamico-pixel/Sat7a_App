<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domain\Authentication\Exceptions\AdminAuthenticationException;
use App\Domain\Authentication\Services\TwoFactorAuthenticationService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\TwoFactorCodeRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Every action here operates on the user attached to the caller's current
 * (narrowly-scoped, short-lived) token — see AdminLoginAction and
 * docs/SECURITY.md §Authentication. Routes are protected by the
 * `abilities:mfa-setup` / `abilities:mfa-challenge` middleware, never by
 * a full `auth:sanctum` token alone.
 */
class TwoFactorController extends Controller
{
    public function setup(Request $request, TwoFactorAuthenticationService $service): JsonResponse
    {
        return ApiResponse::success($service->startSetup($this->user($request)));
    }

    public function confirm(TwoFactorCodeRequest $request, TwoFactorAuthenticationService $service): JsonResponse
    {
        try {
            $recoveryCodes = $service->confirmSetup($this->user($request), $request->string('code')->toString());
        } catch (AdminAuthenticationException $e) {
            return ApiResponse::error($e->errorCode, $e->getMessage(), $e->status);
        }

        return ApiResponse::success([
            'recovery_codes' => $recoveryCodes,
            ...$this->issueFullAccessToken($request),
        ]);
    }

    public function verifyChallenge(TwoFactorCodeRequest $request, TwoFactorAuthenticationService $service): JsonResponse
    {
        try {
            $valid = $service->verifyChallenge($this->user($request), $request->string('code')->toString());
        } catch (AdminAuthenticationException $e) {
            return ApiResponse::error($e->errorCode, $e->getMessage(), $e->status);
        }

        if (! $valid) {
            $e = AdminAuthenticationException::mfaInvalidCode();

            return ApiResponse::error($e->errorCode, $e->getMessage(), $e->status);
        }

        return ApiResponse::success($this->issueFullAccessToken($request));
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }

    /**
     * @return array{user: UserResource, token: string}
     */
    private function issueFullAccessToken(Request $request): array
    {
        $user = $this->user($request);
        $request->user()->currentAccessToken()->delete();

        $token = $user->createToken('admin-session', ['*'], now()->addHours(8));

        return ['user' => new UserResource($user), 'token' => $token->plainTextToken];
    }
}
