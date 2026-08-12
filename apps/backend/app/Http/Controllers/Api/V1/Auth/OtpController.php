<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domain\Authentication\Actions\SendOtpAction;
use App\Domain\Authentication\Actions\VerifyOtpAction;
use App\Domain\Authentication\Exceptions\OtpVerificationException;
use App\Domain\Users\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\SendOtpRequest;
use App\Http\Requests\Api\V1\Auth\VerifyOtpRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class OtpController extends Controller
{
    public function send(SendOtpRequest $request, SendOtpAction $action): JsonResponse
    {
        $action->handle(
            phone: $request->string('phone')->toString(),
            userType: UserType::from($request->string('user_type')->toString()),
            requestIp: $request->ip(),
        );

        // Never reveal whether the phone number is already registered —
        // the response is identical either way.
        return ApiResponse::success(['message' => 'A verification code has been sent.']);
    }

    public function verify(VerifyOtpRequest $request, VerifyOtpAction $action): JsonResponse
    {
        try {
            $result = $action->handle(
                phone: $request->string('phone')->toString(),
                plainCode: $request->string('code')->toString(),
                userType: UserType::from($request->string('user_type')->toString()),
            );
        } catch (OtpVerificationException $e) {
            return ApiResponse::error($e->errorCode, $e->getMessage(), $e->status);
        }

        return ApiResponse::success([
            'user' => new UserResource($result['user']),
            'token' => $result['token'],
        ]);
    }
}
