<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Ledger\Actions\GetProviderBalanceAction;
use App\Domain\Providers\Models\Provider;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\LedgerEntryResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LedgerController extends Controller
{
    public function balance(Provider $provider, GetProviderBalanceAction $action): JsonResponse
    {
        return ApiResponse::success(['balance' => $action->handle($provider)]);
    }

    public function index(Request $request, Provider $provider): JsonResponse
    {
        $entries = $provider->ledgerEntries()
            ->with('order')
            ->latest('created_at')
            ->paginate($request->integer('per_page', 20));

        return ApiResponse::success(
            data: ['entries' => LedgerEntryResource::collection($entries->items())],
            meta: [
                'current_page' => $entries->currentPage(),
                'per_page' => $entries->perPage(),
                'total' => $entries->total(),
            ],
        );
    }
}
