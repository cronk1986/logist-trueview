<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Logis\LogisContext\Infrastructure\Persistence\User\UserModel;

/**
 * @extends Factory<UserModel>
 */
final class UserFactory extends Factory
{
    /** @var class-string<UserModel> */
    protected $model = UserModel::class;

    private static ?string $password = null;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => self::$password ??= Hash::make('password'),
            'remember_token' => Str::random(length: 10),
        ];
    }
}
