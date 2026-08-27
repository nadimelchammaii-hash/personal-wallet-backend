<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $expense = [
            ['name' => 'Food', 'icon' => 'mdi-food', 'color' => '#EF6C00'],
            ['name' => 'Transportation', 'icon' => 'mdi-car', 'color' => '#1E88E5'],
            ['name' => 'Shopping', 'icon' => 'mdi-cart', 'color' => '#8E24AA'],
            ['name' => 'Bills', 'icon' => 'mdi-file-document-outline', 'color' => '#546E7A'],
            ['name' => 'Entertainment', 'icon' => 'mdi-movie-open', 'color' => '#D81B60'],
            ['name' => 'Health', 'icon' => 'mdi-medical-bag', 'color' => '#43A047'],
            ['name' => 'Education', 'icon' => 'mdi-school', 'color' => '#3949AB'],
            ['name' => 'Other', 'icon' => 'mdi-shape', 'color' => '#757575'],
        ];

        $income = [
            ['name' => 'Salary', 'icon' => 'mdi-cash-multiple', 'color' => '#2E7D32'],
            ['name' => 'Other', 'icon' => 'mdi-shape', 'color' => '#757575'],
        ];

        foreach ($expense as $category) {
            $this->createDefault($category, 'expense');
        }

        foreach ($income as $category) {
            $this->createDefault($category, 'income');
        }
    }

    private function createDefault(array $category, string $type): void
    {
        Category::query()->firstOrCreate(
            ['user_id' => null, 'name' => $category['name'], 'type' => $type],
            ['icon' => $category['icon'], 'color' => $category['color'], 'is_default' => true],
        );
    }
}
