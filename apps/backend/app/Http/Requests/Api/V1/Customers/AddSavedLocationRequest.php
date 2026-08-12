<?php

namespace App\Http\Requests\Api\V1\Customers;

use App\Domain\Customers\Enums\SavedLocationLabel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddSavedLocationRequest extends FormRequest
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
            'label' => ['required', 'string', Rule::in(array_map(fn (SavedLocationLabel $l) => $l->value, SavedLocationLabel::cases()))],
            'custom_label' => ['required_if:label,custom', 'nullable', 'string', 'max:100'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'formatted_address' => ['required', 'string', 'max:500'],
            'place_id' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
