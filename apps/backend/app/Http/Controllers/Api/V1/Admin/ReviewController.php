<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Providers\Models\Provider;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ReviewResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function index(Request $request, Provider $provider): JsonResponse
    {
        $reviews = $provider->reviews()
            ->with(['order', 'driver'])
            ->latest('created_at')
            ->paginate($request->integer('per_page', 20));

        return ApiResponse::success(
            data: ['reviews' => ReviewResource::collection($reviews->items())],
            meta: [
                'current_page' => $reviews->currentPage(),
                'per_page' => $reviews->perPage(),
                'total' => $reviews->total(),
            ],
        );
    }
}
