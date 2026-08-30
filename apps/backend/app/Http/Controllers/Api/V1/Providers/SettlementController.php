<?php

namespace App\Http\Controllers\Api\V1\Providers;

use App\Http\Controllers\Concerns\ResolvesProvider;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\SettlementBatchResource;
use App\Http\Responses\ApiResponse;
use App\Support\Enums\ErrorCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettlementController extends Controller
{
    use ResolvesProvider;

    public function index(Request $request): JsonResponse
    {
        // Real Phase 24 finding (docs/PERFORMANCE.md): neither endpoint in
        // this controller eager-loaded `approvedBy`, so
        // SettlementBatchResource's `approved_by` field (whenLoaded)
        // silently came back missing on every response — the provider
        // could never actually see who approved their own settlement.
        $batches = $this->resolveProvider($request)->settlementBatches()
            ->with('approvedBy')
            ->latest('created_at')
            ->paginate($request->integer('per_page', 20));

        return ApiResponse::success(
            data: ['settlements' => SettlementBatchResource::collection($batches->items())],
            meta: [
                'current_page' => $batches->currentPage(),
                'per_page' => $batches->perPage(),
                'total' => $batches->total(),
            ],
        );
    }

    public function show(Request $request, string $settlementPublicId): JsonResponse
    {
        $batch = $this->resolveProvider($request)->settlementBatches()
            ->where('public_id', $settlementPublicId)
            ->with('approvedBy')
            ->first();

        if ($batch === null) {
            return ApiResponse::error(ErrorCode::NotFound, 'Settlement batch not found.', 404);
        }

        return ApiResponse::success(['settlement' => new SettlementBatchResource($batch)]);
    }
}
