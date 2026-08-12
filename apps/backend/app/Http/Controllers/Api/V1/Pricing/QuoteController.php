<?php

namespace App\Http\Controllers\Api\V1\Pricing;

use App\Domain\Pricing\Actions\GenerateQuoteAction;
use App\Domain\Pricing\Enums\PriceType;
use App\Domain\Pricing\Exceptions\PricingException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Pricing\QuoteRequest;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class QuoteController extends Controller
{
    public function store(QuoteRequest $request, GenerateQuoteAction $action): JsonResponse
    {
        if ($request->boolean('requires_manual_quote')) {
            return ApiResponse::success([
                'price_type' => PriceType::ManualQuote->value,
                'message' => 'This situation needs a manual quote — our team will follow up with a price.',
            ]);
        }

        try {
            $snapshot = $action->handle(
                distanceMeters: $request->integer('distance_meters'),
                serviceType: $request->string('service_type')->toString(),
                vehicleCategory: $request->string('vehicle_category')->toString(),
                waitingMinutes: $request->integer('waiting_minutes', 0),
            );
        } catch (PricingException $e) {
            return ApiResponse::error($e->errorCode, $e->getMessage(), $e->status);
        }

        return ApiResponse::success($snapshot->toArray());
    }
}
