<?php

namespace Database\Factories;

use App\Enums\Rol;
use App\Models\User;
use App\Services\BilleteraService;
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
            'rol' => Rol::Consultante,
            'remember_token' => Str::random(10),
        ];
    }

    /** Como en la app: cada consultante nace con su regalo de créditos (y su movimiento). */
    public function configure(): static
    {
        return $this->afterCreating(function (User $user) {
            if ($user->esConsultante()) {
                app(BilleteraService::class)->regalar($user);
            }
        });
    }

    public function tarotista(): static
    {
        return $this->state(fn (array $attributes) => ['rol' => Rol::Tarotista]);
    }

    public function consultante(): static
    {
        return $this->state(fn (array $attributes) => ['rol' => Rol::Consultante]);
    }

    /** Consultante que entró solo con su nombre: sin email ni contraseña. */
    public function invitada(): static
    {
        return $this->state(fn (array $attributes) => [
            'rol' => Rol::Consultante,
            'email' => null,
            'email_verified_at' => null,
            'password' => null,
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

    /**
     * Indicate that the model has two-factor authentication configured.
     */
    public function withTwoFactor(): static {}
}
