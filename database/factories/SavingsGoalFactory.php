<?php

namespace Database\Factories;

use App\Models\SavingsGoal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SavingsGoal>
 */
class SavingsGoalFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(2, true),
            'target_amount' => fake()->randomFloat(2, 100, 5000),
            'current_amount' => 0,
            'target_date' => fake()->dateTimeBetween('+1 month', '+1 year')->format('Y-m-d'),
            'icon' => 'mdi-flag',
            'color' => '#1E88E5',
            'status' => 'active',
        ];
    }
}
