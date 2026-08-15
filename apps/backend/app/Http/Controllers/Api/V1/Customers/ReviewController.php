<?php

namespace App\Http\Controllers\Api\V1\Customers;

use App\Domain\Reviews\Actions\CreateReviewAction;
use App\Domain\Reviews\Exceptions\ReviewException;
use App\Http\Controllers\Concerns\ResolvesCustomer;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customers\CreateReviewRequest;
use App\Http\Resources\Api\V1\ReviewResource;
use App\Http\Responses\ApiResponse;
use App\Support\Enums\ErrorCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    use ResolvesCustomer;

    public function store(CreateReviewRequest $request, string $orderPublicId, CreateReviewAction $action): JsonResponse
    {
        $order = $this->resolveCustomer($request)->orders()->where('public_id', $orderPublicId)->first();

        if ($order === null) {
            return ApiResponse::error(ErrorCode::NotFound, 'Order not found.', 404);
        }

        try {
            $review = $action->handle(
                order: $order,
                rating: $request->integer('rating'),
                comment: $request->string('comment')->toString() ?: null,
            );
        } catch (ReviewException $e) {
            return ApiResponse::error($e->errorCode, $e->getMessage(), $e->status);
        }

        return ApiResponse::success(['review' => new ReviewResource($review)], status: 201);
    }

    public function show(Request $request, string $orderPublicId): JsonResponse
    {
        $order = $this->resolveCustomer($request)->orders()->where('public_id', $orderPublicId)->first();

        if ($order === null) {
            return ApiResponse::error(ErrorCode::NotFound, 'Order not found.', 404);
        }

        $review = $order->review;

        if ($review === null) {
            return ApiResponse::error(ErrorCode::NotFound, 'This order has not been reviewed yet.', 404);
        }

        return ApiResponse::success(['review' => new ReviewResource($review)]);
    }
}
