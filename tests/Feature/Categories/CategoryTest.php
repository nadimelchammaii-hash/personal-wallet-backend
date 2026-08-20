<?php

use App\Models\Category;
use App\Models\User;

test('a guest cannot access categories', function (): void {
    $this->getJson('/api/v1/categories')->assertUnauthorized();
});

test('a user sees system defaults plus their own categories, not other users\' categories', function (): void {
    $user = User::factory()->create();
    Category::factory()->systemDefault()->create(['name' => 'Food', 'type' => 'expense']);
    Category::factory()->for($user)->create(['name' => 'Side Hustle', 'type' => 'income']);
    Category::factory()->create(['name' => 'Someone Elses', 'type' => 'expense']); // another user's

    $response = $this->actingAs($user)->getJson('/api/v1/categories');

    $response->assertOk()->assertJsonCount(2, 'data');
});

test('a user can create their own category', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/v1/categories', [
        'name' => 'Freelance',
        'type' => 'income',
        'icon' => 'mdi-laptop',
        'color' => '#123456',
    ]);

    $response->assertCreated()->assertJsonPath('data.name', 'Freelance');
    $this->assertDatabaseHas('categories', ['user_id' => $user->id, 'name' => 'Freelance']);
});

test('a user can update their own category', function (): void {
    $user = User::factory()->create();
    $category = Category::factory()->for($user)->create(['name' => 'Old Name']);

    $response = $this->actingAs($user)->putJson("/api/v1/categories/{$category->id}", [
        'name' => 'New Name',
        'type' => $category->type,
    ]);

    $response->assertOk()->assertJsonPath('data.name', 'New Name');
});

test('a user cannot update a system default category', function (): void {
    $user = User::factory()->create();
    $category = Category::factory()->systemDefault()->create();

    $this->actingAs($user)->putJson("/api/v1/categories/{$category->id}", [
        'name' => 'Hijacked',
        'type' => $category->type,
    ])->assertForbidden();
});

test('a user cannot update another user\'s category', function (): void {
    $owner = User::factory()->create();
    $category = Category::factory()->for($owner)->create();
    $other = User::factory()->create();

    $this->actingAs($other)->putJson("/api/v1/categories/{$category->id}", [
        'name' => 'Hijacked',
        'type' => $category->type,
    ])->assertForbidden();
});

test('a user can delete their own category', function (): void {
    $user = User::factory()->create();
    $category = Category::factory()->for($user)->create();

    $this->actingAs($user)->deleteJson("/api/v1/categories/{$category->id}")->assertNoContent();
    $this->assertDatabaseMissing('categories', ['id' => $category->id]);
});

test('a user cannot delete a system default category', function (): void {
    $user = User::factory()->create();
    $category = Category::factory()->systemDefault()->create();

    $this->actingAs($user)->deleteJson("/api/v1/categories/{$category->id}")->assertForbidden();
    $this->assertDatabaseHas('categories', ['id' => $category->id]);
});
