<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Fleet\Actions\SuspendTowTruckAction;
use App\Domain\Fleet\Models\TowTruck;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ReasonRequest;
use App\Http\Resources\Api\V1\TowTruckResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class TowTruckController extends Controller
{
    public function suspend(ReasonRequest $request, TowTruck $towTruck, SuspendTowTruckAction $action): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $towTruck = $action->handle($towTruck, $actor, $request->string('reason')->toString());

        return ApiResponse::success(['tow_truck' => new TowTruckResource($towTruck)]);
    }
}
