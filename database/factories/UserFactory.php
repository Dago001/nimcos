<?php

namespace Database\Factories;

use App\Enums\UserStatus;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * TEST DATA ONLY.
 *
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    public const PASSWORD = 'Test-Password-123!';

    protected static ?string $hash = null;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$hash ??= Hash::make(self::PASSWORD),
            'status' => UserStatus::ACTIVE,
            'is_test_data' => true,
            'password_changed_at' => now(),
        ];
    }

    public function withRole(string $roleName): static
    {
        return $this->afterCreating(function (User $user) use ($roleName) {
            $role = Role::query()->where('name', $roleName)->firstOrFail();
            $user->roles()->attach($role->getKey(), ['assigned_at' => now()]);
        });
    }
}
