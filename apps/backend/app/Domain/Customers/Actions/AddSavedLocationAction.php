<?php

namespace App\Domain\Customers\Actions;

use App\Domain\Customers\Enums\SavedLocationLabel;
use App\Domain\Customers\Exceptions\CustomerException;
use App\Domain\Customers\Models\Customer;
use App\Domain\Customers\Models\SavedLocation;
use App\Support\Enums\ErrorCode;

/**
 * A customer can only have one "home" and one "work" saved location — the
 * DB has a partial unique index as the final guard, but this checks first
 * so the caller gets a clean, expected error rather than a raw constraint
 * violation (see docs/PRODUCT_REQUIREMENTS.md and the migration).
 */
class AddSavedLocationAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Customer $customer, array $data): SavedLocation
    {
        $label = SavedLocationLabel::from($data['label']);

        if ($label !== SavedLocationLabel::Custom && $customer->savedLocations()->where('label', $label->value)->exists()) {
            throw new CustomerException(
                ErrorCode::ValidationFailed,
                "A saved '{$label->value}' location already exists — update or delete it first.",
                422,
            );
        }

        $location = new SavedLocation([
            'label' => $label->value,
            'custom_label' => $label === SavedLocationLabel::Custom ? ($data['custom_label'] ?? null) : null,
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'formatted_address' => $data['formatted_address'],
            'place_id' => $data['place_id'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);
        $location->customer_id = $customer->id;
        $location->save();

        return $location;
    }
}
