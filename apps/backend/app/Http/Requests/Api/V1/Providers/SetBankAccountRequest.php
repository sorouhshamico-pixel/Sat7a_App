<?php

namespace App\Http\Requests\Api\V1\Providers;

use Illuminate\Foundation\Http\FormRequest;

class SetBankAccountRequest extends FormRequest
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
            'account_holder_name' => ['required', 'string', 'max:255'],
            // Saudi IBAN: "SA" followed by 22 digits (see
            // docs/SETTLEMENT_ARCHITECTURE.md §Bank account security).
            'iban' => ['required', 'string', 'regex:/^SA\d{22}$/'],
            'bank_name' => ['required', 'string', 'max:255'],
        ];
    }
}
