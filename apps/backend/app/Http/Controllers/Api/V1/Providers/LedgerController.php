<?php

namespace App\Http\Controllers\Api\V1\Providers;

use App\Domain\Ledger\Actions\GetProviderBalanceAction;
use App\Http\Controllers\Concerns\ResolvesProvider;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\LedgerEntryResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LedgerController extends Controller
{
    use ResolvesProvider;

    public function balance(Request $request, GetProviderBalanceAction $action): JsonResponse
    {
        return ApiResponse::success(['balance' => $action->handle($this->resolveProvider($request))]);
    }

    public function index(Request $request): JsonResponse
    {
        $entries = $this->resolveProvider($request)->ledgerEntries()
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
