<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Domain\Authorization\Enums\RoleName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'role' => ['required', 'string', Rule::in(array_map(fn (RoleName $r) => $r->value, RoleName::cases()))],
        ];
    }
}
