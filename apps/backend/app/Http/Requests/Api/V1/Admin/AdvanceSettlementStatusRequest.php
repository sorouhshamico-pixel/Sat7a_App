<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Domain\Ledger\Enums\SettlementStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdvanceSettlementStatusRequest extends FormRequest
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
            'status' => [
                'required',
                'string',
                Rule::in(array_map(fn (SettlementStatus $s) => $s->value, SettlementStatus::cases())),
            ],
            'reference' => ['nullable', 'string', 'max:255'],
            'failure_reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
