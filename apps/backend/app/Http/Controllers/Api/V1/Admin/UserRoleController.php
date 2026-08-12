<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Authorization\Actions\AssignRoleAction;
use App\Domain\Authorization\Actions\RevokeRoleAction;
use App\Domain\Authorization\Models\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\AssignRoleRequest;
use App\Http\Resources\Api\V1\RoleResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Support\Enums\ErrorCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserRoleController extends Controller
{
    public function index(User $user): JsonResponse
    {
        return ApiResponse::success([
            'roles' => RoleResource::collection($user->roles()->with('permissions')->get()),
        ]);
    }

    public function store(AssignRoleRequest $request, User $user, AssignRoleAction $action): JsonResponse
    {
        $role = Role::query()->where('name', $request->string('role')->toString())->firstOrFail();

        /** @var User $actor */
        $actor = $request->user();

        $action->handle($user, $role, $actor);

        return ApiResponse::success(['roles' => RoleResource::collection($user->roles()->with('permissions')->get())]);
    }

    public function destroy(Request $request, User $user, string $roleName, RevokeRoleAction $action): JsonResponse
    {
        $role = Role::query()->where('name', $roleName)->first();

        if ($role === null) {
            return ApiResponse::error(ErrorCode::NotFound, 'Role not found.', 404);
        }

        /** @var User $actor */
        $actor = $request->user();

        $action->handle($user, $role, $actor);

        return ApiResponse::success(['roles' => RoleResource::collection($user->roles()->with('permissions')->get())]);
    }
}
