<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Authorization\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Role
 */
class RoleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->name,
            'scope' => $this->scope,
            'permissions' => $this->whenLoaded(
                'permissions',
                fn () => $this->permissions->pluck('name'),
            ),
        ];
    }
}
