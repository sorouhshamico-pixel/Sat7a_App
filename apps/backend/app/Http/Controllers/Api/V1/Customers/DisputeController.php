<?php

namespace App\Http\Controllers\Api\V1\Customers;

use App\Domain\Disputes\Actions\RaiseDisputeAction;
use App\Domain\Disputes\Enums\DisputeReason;
use App\Domain\Disputes\Exceptions\DisputeException;
use App\Http\Controllers\Concerns\ResolvesCustomer;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customers\RaiseDisputeRequest;
use App\Http\Resources\Api\V1\DisputeResource;
use App\Http\Responses\ApiResponse;
use App\Support\Enums\ErrorCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DisputeController extends Controller
{
    use ResolvesCustomer;

    public function index(Request $request): JsonResponse
    {
        $disputes = $this->resolveCustomer($request)->disputes()
            ->with('order')
            ->latest('created_at')
            ->paginate($request->integer('per_page', 20));

        return ApiResponse::success(
            data: ['disputes' => DisputeResource::collection($disputes->items())],
            meta: [
                'current_page' => $disputes->currentPage(),
                'per_page' => $disputes->perPage(),
                'total' => $disputes->total(),
            ],
        );
    }

    public function show(Request $request, string $disputePublicId): JsonResponse
    {
        $dispute = $this->resolveCustomer($request)->disputes()
            ->where('public_id', $disputePublicId)
            ->with(['order', 'assignedTo', 'resolvedBy'])
            ->first();

        if ($dispute === null) {
            return ApiResponse::error(ErrorCode::NotFound, 'Dispute not found.', 404);
        }

        return ApiResponse::success(['dispute' => new DisputeResource($dispute)]);
    }

    public function store(RaiseDisputeRequest $request, string $orderPublicId, RaiseDisputeAction $action): JsonResponse
    {
        $order = $this->resolveCustomer($request)->orders()->where('public_id', $orderPublicId)->first();

        if ($order === null) {
            return ApiResponse::error(ErrorCode::NotFound, 'Order not found.', 404);
        }

        try {
            $dispute = $action->handle(
                order: $order,
                reason: DisputeReason::from($request->string('reason')->toString()),
                description: $request->string('description')->toString(),
            );
        } catch (DisputeException $e) {
            return ApiResponse::error($e->errorCode, $e->getMessage(), $e->status);
        }

        return ApiResponse::success(['dispute' => new DisputeResource($dispute)], status: 201);
    }
}
