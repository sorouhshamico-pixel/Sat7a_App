<?php

namespace Database\Factories;

use App\Domain\Users\Enums\UserStatus;
use App\Domain\Users\Enums\UserType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone' => '+9665'.fake()->unique()->numerify('########'),
            'phone_verified_at' => now(),
            'user_type' => UserType::Customer,
            'status' => UserStatus::Active,
            'remember_token' => Str::random(10),
        ];
    }

    public function customer(): static
    {
        return $this->state(fn () => [
            'user_type' => UserType::Customer,
            'email' => null,
            'password' => null,
        ]);
    }

    public function providerStaff(): static
    {
        return $this->state(fn () => [
            'user_type' => UserType::ProviderStaff,
            'email' => null,
            'password' => null,
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn () => [
            'user_type' => UserType::AdminStaff,
            'phone' => null,
            'phone_verified_at' => null,
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['status' => UserStatus::Suspended]);
    }
}
