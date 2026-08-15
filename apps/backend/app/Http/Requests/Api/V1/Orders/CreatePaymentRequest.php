<?php

namespace App\Http\Requests\Api\V1\Orders;

use App\Domain\Payments\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreatePaymentRequest extends FormRequest
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
            'method' => ['required', 'string', Rule::in(array_map(fn (PaymentMethod $m) => $m->value, PaymentMethod::cases()))],
        ];
    }
}
