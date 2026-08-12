<?php

namespace App\Http\Requests\Api\V1\Maps;

use Illuminate\Foundation\Http\FormRequest;

class GeocodeRequest extends FormRequest
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
            'address' => ['required', 'string', 'max:500'],
        ];
    }
}
