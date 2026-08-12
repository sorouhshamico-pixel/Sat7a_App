<?php

namespace App\Http\Requests\Api\V1\Orders;

use App\Domain\Fleet\Enums\ServiceCapability;
use App\Domain\Pricing\Enums\VehicleCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateOrderRequest extends FormRequest
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
            'vehicle_id' => ['required', 'string'],
            'service_type' => ['required', 'string', Rule::in(array_map(fn (ServiceCapability $c) => $c->value, ServiceCapability::cases()))],
            'vehicle_category' => ['required', 'string', Rule::in(array_map(fn (VehicleCategory $c) => $c->value, VehicleCategory::cases()))],

            'pickup_latitude' => ['required', 'numeric', 'between:-90,90'],
            'pickup_longitude' => ['required', 'numeric', 'between:-180,180'],
            'pickup_formatted_address' => ['required', 'string', 'max:500'],

            'dropoff_latitude' => ['required', 'numeric', 'between:-90,90'],
            'dropoff_longitude' => ['required', 'numeric', 'between:-180,180'],
            'dropoff_formatted_address' => ['required', 'string', 'max:500'],

            'notes' => ['nullable', 'string', 'max:1000'],

            // The customer/UI flags an unusual situation (severely damaged
            // vehicle, no wheels, underground parking, ...) — see
            // docs/PRICING_ENGINE.md §Manual quote. There is no automated
            // order-creation path for these yet; the endpoint rejects with
            // MANUAL_QUOTE_REQUIRED and the customer is told to contact
            // support (see docs/ORDER_LIFECYCLE.md).
            'requires_manual_quote' => ['sometimes', 'boolean'],
        ];
    }
}
