<?php

namespace App\Http\Requests\Api\V1\Maps;

use Illuminate\Foundation\Http\FormRequest;

class AutocompleteRequest extends FormRequest
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
            'query' => ['required', 'string', 'max:255'],
            'session_token' => ['nullable', 'string', 'max:255'],
        ];
    }
}
