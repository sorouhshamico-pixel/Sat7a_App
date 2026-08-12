<?php

namespace App\Http\Requests\Api\V1\Providers;

use Illuminate\Foundation\Http\FormRequest;

class RegisterProviderRequest extends FormRequest
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
            'business_name' => ['required', 'string', 'max:255'],
            'owner_name' => ['required', 'string', 'max:255'],
            'contact_phone' => ['required', 'string', 'regex:/^\+[1-9]\d{6,14}$/', 'unique:users,phone'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'commercial_registration_number' => ['nullable', 'string', 'max:100'],
            'tax_number' => ['nullable', 'string', 'max:100'],
        ];
    }
}
