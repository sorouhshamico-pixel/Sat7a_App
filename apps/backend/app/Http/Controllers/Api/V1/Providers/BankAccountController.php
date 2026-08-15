<?php

namespace App\Http\Controllers\Api\V1\Providers;

use App\Domain\Ledger\Actions\SetProviderBankAccountAction;
use App\Http\Controllers\Concerns\ResolvesProvider;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Providers\SetBankAccountRequest;
use App\Http\Resources\Api\V1\ProviderBankAccountResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BankAccountController extends Controller
{
    use ResolvesProvider;

    public function show(Request $request): JsonResponse
    {
        $bankAccount = $this->resolveProvider($request)->bankAccount;

        return ApiResponse::success(['bank_account' => $bankAccount ? new ProviderBankAccountResource($bankAccount) : null]);
    }

    public function update(SetBankAccountRequest $request, SetProviderBankAccountAction $action): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $bankAccount = $action->handle(
            provider: $this->resolveProvider($request),
            data: $request->only(['account_holder_name', 'iban', 'bank_name']),
            actor: $actor,
        );

        return ApiResponse::success(['bank_account' => new ProviderBankAccountResource($bankAccount)]);
    }
}
