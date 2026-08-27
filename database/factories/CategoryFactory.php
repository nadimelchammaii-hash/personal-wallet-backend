<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
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
            'name' => fake()->unique()->word(),
            'type' => fake()->randomElement(Category::TYPES),
            'icon' => 'mdi-shape',
            'color' => '#757575',
            'is_default' => false,
        ];
    }

    public function systemDefault(): static
    {
        return $this->state(fn (array $attributes) => ['user_id' => null, 'is_default' => true]);
    }
}
