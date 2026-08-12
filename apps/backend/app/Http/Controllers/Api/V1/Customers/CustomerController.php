<?php

namespace App\Http\Controllers\Api\V1\Customers;

use App\Domain\Customers\Actions\StorePublicImageAction;
use App\Domain\Customers\Actions\UpdateCustomerProfileAction;
use App\Http\Controllers\Concerns\ResolvesCustomer;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customers\UpdateCustomerProfileRequest;
use App\Http\Requests\Api\V1\Customers\UploadAvatarRequest;
use App\Http\Resources\Api\V1\CustomerResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    use ResolvesCustomer;

    public function show(Request $request): JsonResponse
    {
        $customer = $this->resolveCustomer($request)->load('user');

        return ApiResponse::success(['customer' => new CustomerResource($customer)]);
    }

    public function update(UpdateCustomerProfileRequest $request, UpdateCustomerProfileAction $action): JsonResponse
    {
        $customer = $this->resolveCustomer($request);
        $customer = $action->handle($customer, $request->validated());

        return ApiResponse::success(['customer' => new CustomerResource($customer)]);
    }

    public function uploadAvatar(UploadAvatarRequest $request, StorePublicImageAction $action): JsonResponse
    {
        $customer = $this->resolveCustomer($request);

        $path = $action->handle($request->file('avatar'), 'avatars');
        $customer->update(['avatar_path' => $path]);

        return ApiResponse::success(['customer' => new CustomerResource($customer->load('user'))]);
    }
}
