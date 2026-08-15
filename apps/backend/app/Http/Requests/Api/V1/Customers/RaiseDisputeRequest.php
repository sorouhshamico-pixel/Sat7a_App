<?php

namespace App\Http\Requests\Api\V1\Customers;

use App\Domain\Disputes\Enums\DisputeReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RaiseDisputeRequest extends FormRequest
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
            'reason' => [
                'required',
                'string',
                Rule::in(array_map(fn (DisputeReason $r) => $r->value, DisputeReason::cases())),
            ],
            'description' => ['required', 'string', 'max:2000'],
        ];
    }
}
