<?php

namespace App\Http\Requests\Api\V1\Auth;

use App\Domain\Users\Enums\UserType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendOtpRequest extends FormRequest
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
            'phone' => ['required', 'string', 'regex:/^\+[1-9]\d{6,14}$/'],
            'user_type' => ['required', Rule::in([UserType::Customer->value, UserType::ProviderStaff->value])],
        ];
    }
}
