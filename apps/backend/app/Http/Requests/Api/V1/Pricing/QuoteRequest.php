<?php

namespace App\Http\Requests\Api\V1\Pricing;

use App\Domain\Fleet\Enums\ServiceCapability;
use App\Domain\Pricing\Enums\VehicleCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Public — a guest builds a quote before authenticating (see
 * docs/PRODUCT_REQUIREMENTS.md §Core customer journey).
 */
class QuoteRequest extends FormRequest
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
            'distance_meters' => ['required', 'integer', 'min:0', 'max:500000'],
            'service_type' => ['required', 'string', Rule::in(array_map(fn (ServiceCapability $c) => $c->value, ServiceCapability::cases()))],
            'vehicle_category' => ['required', 'string', Rule::in(array_map(fn (VehicleCategory $c) => $c->value, VehicleCategory::cases()))],
            'waiting_minutes' => ['sometimes', 'integer', 'min:0', 'max:1440'],
            // The customer/UI flags an unusual situation (severely damaged
            // vehicle, no wheels, underground parking, ...) — see
            // docs/PRICING_ENGINE.md §Manual quote.
            'requires_manual_quote' => ['sometimes', 'boolean'],
        ];
    }
}
