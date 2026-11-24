<?php

namespace Database\Factories;

use App\Domain\Master\Models\Cabang;
use App\Domain\Shared\Enums\Role;
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
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => Role::AdminPusat,
            'aktif' => true,
            'wajib_ganti_password' => false,
        ];
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

    public function superadmin(): static
    {
        return $this->state(fn () => ['role' => Role::Superadmin, 'cabang_id' => null]);
    }

    public function adminPusat(): static
    {
        return $this->state(fn () => ['role' => Role::AdminPusat, 'cabang_id' => null]);
    }

    public function adminCabang(?Cabang $cabang = null): static
    {
        return $this->state(fn () => [
            'role' => Role::AdminCabang,
            'cabang_id' => $cabang?->id ?? Cabang::factory(),
        ]);
    }

    public function mentor(): static
    {
        return $this->state(fn () => ['role' => Role::Mentor, 'cabang_id' => null]);
    }

    public function nonaktif(): static
    {
        return $this->state(fn () => ['aktif' => false]);
    }
}
