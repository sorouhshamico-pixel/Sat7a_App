<?php

namespace App\Http\Requests\Api\V1\Providers;

use App\Domain\Fleet\Enums\ServiceCapability;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddTowTruckRequest extends FormRequest
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
            'manufacturer' => ['required', 'string', 'max:100'],
            'model' => ['required', 'string', 'max:100'],
            'year' => ['required', 'integer', 'min:1980', 'max:'.(date('Y') + 1)],
            'plate_number' => ['required', 'string', 'max:20', 'unique:tow_trucks,plate_number'],
            'capacity' => ['nullable', 'string', 'max:50'],
            'service_capabilities' => ['required', 'array', 'min:1'],
            'service_capabilities.*' => [Rule::in(array_map(fn (ServiceCapability $c) => $c->value, ServiceCapability::cases()))],
        ];
    }
}
