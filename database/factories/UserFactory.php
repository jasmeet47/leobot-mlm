<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'username' => 'TX' . fake()->unique()->numerify('######'),
            'sponsor_id' => null,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('##########'),
            'password' => static::$password ??= Hash::make('password'),
            'security_pin' => null,
            'status' => 'inactive',
            'remember_token' => Str::random(10),
        ];
    }
}
