<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Disputes\Actions\AdvanceDisputeStatusAction;
use App\Domain\Disputes\Enums\DisputeStatus;
use App\Domain\Disputes\Exceptions\DisputeException;
use App\Domain\Disputes\Models\Dispute;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\AdvanceDisputeStatusRequest;
use App\Http\Resources\Api\V1\DisputeResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DisputeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Dispute::query()->with(['order', 'assignedTo'])->latest('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        $disputes = $query->paginate($request->integer('per_page', 20));

        return ApiResponse::success(
            data: ['disputes' => DisputeResource::collection($disputes->items())],
            meta: [
                'current_page' => $disputes->currentPage(),
                'per_page' => $disputes->perPage(),
                'total' => $disputes->total(),
            ],
        );
    }

    public function show(Dispute $dispute): JsonResponse
    {
        $dispute->load(['order', 'assignedTo', 'resolvedBy']);

        return ApiResponse::success(['dispute' => new DisputeResource($dispute)]);
    }

    public function advance(AdvanceDisputeStatusRequest $request, Dispute $dispute, AdvanceDisputeStatusAction $action): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        try {
            $dispute = $action->handle(
                dispute: $dispute,
                to: DisputeStatus::from($request->string('status')->toString()),
                actor: $actor,
                resolutionNotes: $request->string('resolution_notes')->toString() ?: null,
            );
        } catch (DisputeException $e) {
            return ApiResponse::error($e->errorCode, $e->getMessage(), $e->status);
        }

        return ApiResponse::success(['dispute' => new DisputeResource($dispute)]);
    }
}
