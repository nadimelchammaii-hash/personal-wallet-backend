<?php

namespace Database\Factories;

use App\Models\GoalContribution;
use App\Models\SavingsGoal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GoalContribution>
 */
class GoalContributionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'goal_id' => SavingsGoal::factory(),
            'amount' => fake()->randomFloat(2, 10, 200),
            'contributed_at' => fake()->dateTimeBetween('-1 month')->format('Y-m-d'),
            'note' => fake()->optional()->sentence(),
        ];
    }
}
