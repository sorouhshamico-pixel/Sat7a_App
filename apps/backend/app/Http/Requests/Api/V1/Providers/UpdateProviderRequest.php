<?php

namespace App\Http\Requests\Api\V1\Providers;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProviderRequest extends FormRequest
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
            'business_name' => ['sometimes', 'string', 'max:255'],
            'contact_email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'commercial_registration_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'tax_number' => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
    }
}
