<?php

namespace App\Domain\Dispatch\Models;

use App\Domain\Dispatch\Enums\DispatchOfferStatus;
use App\Domain\Drivers\Models\Driver;
use App\Domain\Fleet\Models\TowTruck;
use App\Domain\Orders\Models\Order;
use App\Domain\Providers\Models\Provider;
use App\Support\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property DispatchOfferStatus $status
 * @property Carbon $expires_at
 * @property Carbon|null $responded_at
 */
#[Fillable(['order_id', 'tow_truck_id', 'driver_id', 'provider_id', 'wave', 'distance_meters', 'expires_at'])]
class DispatchOffer extends Model
{
    use HasUlid;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DispatchOfferStatus::class,
            'expires_at' => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<TowTruck, $this>
     */
    public function towTruck(): BelongsTo
    {
        return $this->belongsTo(TowTruck::class);
    }

    /**
     * @return BelongsTo<Driver, $this>
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    /**
     * @return BelongsTo<Provider, $this>
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }
}
