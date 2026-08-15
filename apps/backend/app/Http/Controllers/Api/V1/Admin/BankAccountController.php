<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Ledger\Actions\VerifyProviderBankAccountAction;
use App\Domain\Ledger\Exceptions\SettlementException;
use App\Domain\Providers\Models\Provider;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ProviderBankAccountResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BankAccountController extends Controller
{
    public function show(Provider $provider): JsonResponse
    {
        $bankAccount = $provider->bankAccount;

        if ($bankAccount === null) {
            return ApiResponse::error(SettlementException::bankAccountNotFound()->errorCode, 'This provider has no bank account on file.', 404);
        }

        return ApiResponse::success(['bank_account' => new ProviderBankAccountResource($bankAccount)]);
    }

    public function verify(Provider $provider, VerifyProviderBankAccountAction $action, Request $request): JsonResponse
    {
        $bankAccount = $provider->bankAccount;

        if ($bankAccount === null) {
            return ApiResponse::error(SettlementException::bankAccountNotFound()->errorCode, 'This provider has no bank account on file.', 404);
        }

        /** @var User $actor */
        $actor = $request->user();

        $bankAccount = $action->handle($bankAccount, $actor);

        return ApiResponse::success(['bank_account' => new ProviderBankAccountResource($bankAccount)]);
    }
}
