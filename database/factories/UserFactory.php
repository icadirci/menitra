<?php

namespace Database\Factories;

use App\Enums\PlatformRole;
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

    /** Base32 TOTP secret used by withTwoFactor() (tests only). */
    public const TWO_FACTOR_SECRET = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Platform users have two-factor set up, as /admin requires it; use
     * withoutTwoFactor() for one that hasn't set it up yet.
     */
    public function superAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'platform_role' => PlatformRole::SuperAdmin,
        ])->withTwoFactor();
    }

    public function platformStaff(): static
    {
        return $this->state(fn (array $attributes) => [
            'platform_role' => PlatformRole::PlatformStaff,
        ])->withTwoFactor();
    }

    /**
     * Two-factor (TOTP) set up with a known test secret and recovery codes
     * `recovery-code-1` … `recovery-code-8` (stored hashed, like Filament does).
     */
    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'app_authentication_secret' => self::TWO_FACTOR_SECRET,
            'app_authentication_recovery_codes' => array_map(
                fn (int $i): string => Hash::make("recovery-code-{$i}"),
                range(1, 8),
            ),
        ]);
    }

    public function withoutTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'app_authentication_secret' => null,
            'app_authentication_recovery_codes' => null,
        ]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
