<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Ledger\Actions\AdvanceSettlementStatusAction;
use App\Domain\Ledger\Actions\GenerateSettlementBatchAction;
use App\Domain\Ledger\Enums\SettlementStatus;
use App\Domain\Ledger\Exceptions\SettlementException;
use App\Domain\Ledger\Models\SettlementBatch;
use App\Domain\Providers\Models\Provider;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\AdvanceSettlementStatusRequest;
use App\Http\Requests\Api\V1\Admin\GenerateSettlementBatchRequest;
use App\Http\Resources\Api\V1\SettlementBatchResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class SettlementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = SettlementBatch::query()->with('provider')->latest('created_at');

        if ($request->filled('provider_id')) {
            $provider = Provider::query()->where('public_id', $request->string('provider_id')->toString())->first();
            $query->where('provider_id', $provider->id ?? 0);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        $batches = $query->paginate($request->integer('per_page', 20));

        return ApiResponse::success(
            data: ['settlements' => SettlementBatchResource::collection($batches->items())],
            meta: [
                'current_page' => $batches->currentPage(),
                'per_page' => $batches->perPage(),
                'total' => $batches->total(),
            ],
        );
    }

    public function show(SettlementBatch $settlement): JsonResponse
    {
        $settlement->load(['provider', 'approvedBy']);

        return ApiResponse::success(['settlement' => new SettlementBatchResource($settlement)]);
    }

    public function store(GenerateSettlementBatchRequest $request, Provider $provider, GenerateSettlementBatchAction $action): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        try {
            $batch = $action->handle(
                provider: $provider,
                periodStart: Carbon::parse($request->string('period_start')->toString()),
                periodEnd: Carbon::parse($request->string('period_end')->toString()),
                actor: $actor,
            );
        } catch (SettlementException $e) {
            return ApiResponse::error($e->errorCode, $e->getMessage(), $e->status);
        }

        return ApiResponse::success(['settlement' => new SettlementBatchResource($batch)], status: 201);
    }

    public function advance(AdvanceSettlementStatusRequest $request, SettlementBatch $settlement, AdvanceSettlementStatusAction $action): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        try {
            $settlement = $action->handle(
                batch: $settlement,
                to: SettlementStatus::from($request->string('status')->toString()),
                actor: $actor,
                reference: $request->string('reference')->toString() ?: null,
                failureReason: $request->string('failure_reason')->toString() ?: null,
            );
        } catch (SettlementException $e) {
            return ApiResponse::error($e->errorCode, $e->getMessage(), $e->status);
        }

        return ApiResponse::success(['settlement' => new SettlementBatchResource($settlement)]);
    }
}
