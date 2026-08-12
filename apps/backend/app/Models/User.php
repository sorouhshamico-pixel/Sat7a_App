<?php

namespace App\Models;

use App\Domain\Authorization\Concerns\HasRoles;
use App\Domain\Providers\Models\Provider;
use App\Domain\Users\Enums\UserStatus;
use App\Domain\Users\Enums\UserType;
use App\Support\Concerns\HasUlid;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property string $public_id
 * @property string|null $phone
 * @property Carbon|null $phone_verified_at
 * @property UserType $user_type
 * @property UserStatus $status
 * @property int|null $provider_id
 * @property string|null $two_factor_secret
 * @property list<string>|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 */
#[Fillable(['name', 'phone', 'email', 'password', 'user_type', 'status', 'provider_id', 'locale'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, HasUlid, Notifiable;

    /**
     * The provider this provider_staff user (owner, fleet manager, or
     * driver) belongs to — null for customers and admin_staff. See
     * docs/DATABASE_SCHEMA.md.
     *
     * @return BelongsTo<Provider, $this>
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'password' => 'hashed',
            'user_type' => UserType::class,
            'status' => UserStatus::class,
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
        ];
    }

    public function hasMfaEnabled(): bool
    {
        return $this->two_factor_confirmed_at !== null;
    }
}
