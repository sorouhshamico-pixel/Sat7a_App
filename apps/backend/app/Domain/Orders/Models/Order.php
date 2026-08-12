<?php

namespace App\Domain\Orders\Models;

use App\Domain\Customers\Models\Customer;
use App\Domain\Customers\Models\Vehicle;
use App\Domain\Drivers\Models\Driver;
use App\Domain\Fleet\Models\TowTruck;
use App\Domain\Orders\Enums\OrderCancelledBy;
use App\Domain\Orders\Enums\OrderPaymentMethod;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Providers\Models\Provider;
use App\Support\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property OrderStatus $status
 * @property OrderPaymentMethod $payment_method
 * @property OrderCancelledBy|null $cancelled_by
 * @property array<string, mixed> $pricing_snapshot
 */
#[Fillable(['service_type', 'pickup_latitude', 'pickup_longitude', 'pickup_formatted_address', 'dropoff_latitude', 'dropoff_longitude', 'dropoff_formatted_address', 'notes', 'pricing_snapshot', 'quoted_price', 'payment_method'])]
class Order extends Model
{
    use HasUlid;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'payment_method' => OrderPaymentMethod::class,
            'cancelled_by' => OrderCancelledBy::class,
            'pricing_snapshot' => 'array',
            'pickup_latitude' => 'decimal:7',
            'pickup_longitude' => 'decimal:7',
            'dropoff_latitude' => 'decimal:7',
            'dropoff_longitude' => 'decimal:7',
            'accepted_at' => 'datetime',
            'arrived_at' => 'datetime',
            'trip_started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * @return BelongsTo<Provider, $this>
     */
    public function assignedProvider(): BelongsTo
    {
        return $this->belongsTo(Provider::class, 'assigned_provider_id');
    }

    /**
     * @return BelongsTo<Driver, $this>
     */
    public function assignedDriver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'assigned_driver_id');
    }

    /**
     * @return BelongsTo<TowTruck, $this>
     */
    public function assignedTowTruck(): BelongsTo
    {
        return $this->belongsTo(TowTruck::class, 'assigned_tow_truck_id');
    }

    /**
     * @return HasMany<OrderStatusHistory, $this>
     */
    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('created_at');
    }
}
