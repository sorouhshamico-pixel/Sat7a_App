<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Domain\Disputes\Enums\DisputeStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdvanceDisputeStatusRequest extends FormRequest
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
                Rule::in(array_map(fn (DisputeStatus $s) => $s->value, DisputeStatus::cases())),
            ],
            'resolution_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
