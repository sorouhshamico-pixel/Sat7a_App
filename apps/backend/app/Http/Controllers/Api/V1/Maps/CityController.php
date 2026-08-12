<?php

namespace App\Http\Controllers\Api\V1\Maps;

use App\Domain\Maps\Models\City;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CityResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Public — the frontend uses this to know which cities are launched
 * without hardcoding "Riyadh" (see spec §152).
 */
class CityController extends Controller
{
    public function index(): JsonResponse
    {
        $cities = City::query()->where('is_active', true)->orderBy('name')->get();

        return ApiResponse::success(['cities' => CityResource::collection($cities)]);
    }
}
