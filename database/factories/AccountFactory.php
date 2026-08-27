<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement(Account::TYPES);
        $balance = fake()->randomFloat(2, -500, 5000);

        return [
            'user_id' => User::factory(),
            'name' => fake()->words(2, true),
            'type' => $type,
            'currency' => 'USD',
            'initial_balance' => $balance,
            'current_balance' => $balance,
            'icon' => Account::DEFAULT_ICONS[$type],
            'is_archived' => false,
        ];
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes) => ['is_archived' => true]);
    }
}
