<?php

namespace App\Domain\Tracking\Models;

use App\Domain\Orders\Models\Order;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Append-only — see docs/DATABASE_SCHEMA.md §Immutability. Written only
 * by App\Domain\Tracking\Actions\RecordLocationPingAction.
 *
 * @property Carbon $recorded_at
 */
#[Fillable(['order_id', 'latitude', 'longitude', 'heading', 'speed_kmh', 'recorded_at'])]
class OrderLocationPing extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'recorded_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
