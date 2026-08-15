<?php

namespace App\Http\Requests\Api\V1\Drivers;

use Illuminate\Foundation\Http\FormRequest;

class RecordLocationPingRequest extends FormRequest
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
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'heading' => ['nullable', 'integer', 'between:0,360'],
            'speed_kmh' => ['nullable', 'integer', 'min:0', 'max:300'],
            'recorded_at' => ['nullable', 'date'],
        ];
    }
}
