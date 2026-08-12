<?php

namespace App\Domain\Drivers\Models;

use App\Domain\Compliance\Models\Document;
use App\Domain\Drivers\Enums\DriverStatus;
use App\Domain\Fleet\Models\TowTruck;
use App\Domain\Providers\Models\Provider;
use App\Models\User;
use App\Support\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * @property DriverStatus $status
 * @property Carbon|null $license_expires_at
 */
#[Fillable(['nationality', 'license_number', 'license_expires_at', 'status', 'is_available', 'rating'])]
class Driver extends Model
{
    use HasUlid;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DriverStatus::class,
            'is_available' => 'boolean',
            'license_expires_at' => 'date',
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
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasOne<TowTruck, $this>
     */
    public function towTruck(): HasOne
    {
        return $this->hasOne(TowTruck::class);
    }

    /**
     * @return MorphMany<Document, $this>
     */
    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }
}
