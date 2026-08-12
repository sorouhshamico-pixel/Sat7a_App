<?php

namespace App\Http\Requests\Api\V1\Providers;

use App\Domain\Fleet\Enums\TowTruckStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTowTruckStatusRequest extends FormRequest
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
            'status' => ['required', 'string', Rule::in(array_map(fn (TowTruckStatus $s) => $s->value, TowTruckStatus::cases()))],
        ];
    }
}
