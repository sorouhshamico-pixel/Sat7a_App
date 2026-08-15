<?php

namespace App\Http\Requests\Api\V1\Drivers;

use App\Domain\Orders\Actions\AdvanceTripStatusAction;
use App\Domain\Orders\Enums\OrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdvanceTripStatusRequest extends FormRequest
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
                Rule::in(array_map(fn (OrderStatus $s) => $s->value, AdvanceTripStatusAction::DRIVER_ADVANCEABLE_STATUSES)),
            ],
        ];
    }
}
