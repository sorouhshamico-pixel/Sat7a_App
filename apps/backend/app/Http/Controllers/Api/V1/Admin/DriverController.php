<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Drivers\Actions\SuspendDriverAction;
use App\Domain\Drivers\Models\Driver;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ReasonRequest;
use App\Http\Resources\Api\V1\DriverResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class DriverController extends Controller
{
    public function suspend(ReasonRequest $request, Driver $driver, SuspendDriverAction $action): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $driver = $action->handle($driver, $actor, $request->string('reason')->toString());

        return ApiResponse::success(['driver' => new DriverResource($driver->load('user'))]);
    }
}
