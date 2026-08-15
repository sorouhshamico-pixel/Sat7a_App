<?php

namespace App\Domain\Customers\Models;

use App\Domain\Disputes\Models\Dispute;
use App\Domain\Orders\Models\Order;
use App\Domain\Reviews\Models\Review;
use App\Models\User;
use App\Support\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property array<string, mixed>|null $preferences
 * @property array<string, mixed>|null $notification_preferences
 */
#[Fillable(['avatar_path', 'preferences', 'notification_preferences'])]
class Customer extends Model
{
    use HasUlid;

    private const DEFAULT_NOTIFICATION_PREFERENCES = [
        'sms' => true,
        'push' => true,
        'email' => true,
        'whatsapp' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'preferences' => 'array',
            'notification_preferences' => 'array',
        ];
    }

    /**
     * @return array<string, bool>
     */
    public static function defaultNotificationPreferences(): array
    {
        return self::DEFAULT_NOTIFICATION_PREFERENCES;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Vehicle, $this>
     */
    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    /**
     * @return HasMany<SavedLocation, $this>
     */
    public function savedLocations(): HasMany
    {
        return $this->hasMany(SavedLocation::class);
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * @return HasMany<Review, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * @return HasMany<Dispute, $this>
     */
    public function disputes(): HasMany
    {
        return $this->hasMany(Dispute::class);
    }
}
