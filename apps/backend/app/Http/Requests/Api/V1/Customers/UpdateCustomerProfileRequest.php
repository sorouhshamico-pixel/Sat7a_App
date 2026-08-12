<?php

namespace App\Http\Requests\Api\V1\Customers;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerProfileRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->user()->id)],
            'locale' => ['sometimes', 'string', 'in:ar,en'],
            'preferences' => ['sometimes', 'array'],
            'notification_preferences' => ['sometimes', 'array'],
            'notification_preferences.sms' => ['sometimes', 'boolean'],
            'notification_preferences.push' => ['sometimes', 'boolean'],
            'notification_preferences.email' => ['sometimes', 'boolean'],
            'notification_preferences.whatsapp' => ['sometimes', 'boolean'],
        ];
    }
}
