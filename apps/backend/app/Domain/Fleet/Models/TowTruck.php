<?php

namespace App\Domain\Fleet\Models;

use App\Domain\Drivers\Models\Driver;
use App\Domain\Fleet\Enums\ServiceCapability;
use App\Domain\Fleet\Enums\TowTruckStatus;
use App\Domain\Providers\Models\Provider;
use App\Support\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property TowTruckStatus $status
 * @property list<ServiceCapability> $service_capabilities
 * @property Carbon|null $last_location_at
 */
#[Fillable(['driver_id', 'manufacturer', 'model', 'year', 'plate_number', 'capacity', 'service_capabilities', 'status', 'current_latitude', 'current_longitude', 'last_location_at'])]
class TowTruck extends Model
{
    use HasUlid;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TowTruckStatus::class,
            'service_capabilities' => 'array',
            'current_latitude' => 'decimal:7',
            'current_longitude' => 'decimal:7',
            'last_location_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Provider, $this>
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    /**
     * @return BelongsTo<Driver, $this>
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }
}
