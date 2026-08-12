<?php

namespace App\Domain\Customers\Models;

use App\Domain\Customers\Enums\SavedLocationLabel;
use App\Support\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property SavedLocationLabel $label
 */
#[Fillable(['label', 'custom_label', 'latitude', 'longitude', 'formatted_address', 'place_id', 'notes'])]
class SavedLocation extends Model
{
    use HasUlid;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'label' => SavedLocationLabel::class,
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
