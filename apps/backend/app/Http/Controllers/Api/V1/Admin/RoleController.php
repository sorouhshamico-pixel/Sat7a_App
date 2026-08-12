<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Authorization\Models\Role;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\RoleResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class RoleController extends Controller
{
    public function index(): JsonResponse
    {
        $roles = Role::query()->with('permissions')->orderBy('name')->get();

        return ApiResponse::success(['roles' => RoleResource::collection($roles)]);
    }
}
