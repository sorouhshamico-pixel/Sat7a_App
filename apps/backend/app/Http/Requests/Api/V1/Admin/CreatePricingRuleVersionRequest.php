<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Domain\Fleet\Enums\ServiceCapability;
use App\Domain\Pricing\Enums\VehicleCategory;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class CreatePricingRuleVersionRequest extends FormRequest
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
        $serviceTypes = array_map(fn (ServiceCapability $c) => $c->value, ServiceCapability::cases());
        $vehicleCategories = array_map(fn (VehicleCategory $c) => $c->value, VehicleCategory::cases());

        return [
            'version_label' => ['required', 'string', 'max:100', 'unique:pricing_rule_versions,version_label'],
            'base_fee' => ['required', 'integer', 'min:0'],
            'minimum_fare' => ['required', 'integer', 'min:0'],
            'distance_rate_per_km' => ['required', 'integer', 'min:0'],

            'service_type_fees' => ['sometimes', 'array', $this->validKeysRule('service_type_fees', $serviceTypes)],
            'service_type_fees.*' => ['integer', 'min:0'],

            'vehicle_category_multipliers' => ['sometimes', 'array', $this->validKeysRule('vehicle_category_multipliers', $vehicleCategories)],
            'vehicle_category_multipliers.*' => ['numeric', 'min:0'],

            'night_fee' => ['sometimes', 'integer', 'min:0'],
            'night_start_hour' => ['sometimes', 'integer', 'between:0,23'],
            'night_end_hour' => ['sometimes', 'integer', 'between:0,23'],

            'waiting_fee_per_minute' => ['sometimes', 'integer', 'min:0'],
            'free_waiting_minutes' => ['sometimes', 'integer', 'min:0'],

            'zone_fee' => ['sometimes', 'integer', 'min:0'],
            'special_condition_fee' => ['sometimes', 'integer', 'min:0'],

            'platform_service_fee_percentage' => ['required', 'numeric', 'between:0,1'],
            'vat_percentage' => ['required', 'numeric', 'between:0,1'],

            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Laravel's `array` rule only validates the values, not the keys — this
     * rejects any key that isn't a real enum value.
     *
     * @param  list<string>  $validKeys
     */
    private function validKeysRule(string $field, array $validKeys): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($field, $validKeys): void {
            $invalid = array_diff(array_keys((array) $value), $validKeys);

            if ($invalid !== []) {
                $fail("The {$field} contains invalid keys: ".implode(', ', $invalid));
            }
        };
    }
}
