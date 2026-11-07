<?php

use App\Models\User;
use Database\Seeders\CategorySeeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    $this->seed(CategorySeeder::class);
});

test('a guest can start a demo session with seeded data and is logged in', function (): void {
    $response = $this->postJson('/api/v1/demo-login');

    $response->assertCreated()
        ->assertJsonPath('data.is_demo', true)
        ->assertJsonPath('data.currency', 'USD')
        ->assertJsonPath('data.timezone', 'UTC');

    $user = User::where('is_demo', true)->firstOrFail();
    $this->assertAuthenticatedAs($user);

    expect($user->accounts()->count())->toBe(3)
        ->and($user->transactions()->count())->toBeGreaterThan(0)
        ->and($user->budgets()->count())->toBe(3)
        ->and($user->savingsGoals()->count())->toBe(3);
});

test('each demo login creates its own isolated account', function (): void {
    $first = $this->postJson('/api/v1/demo-login')->json('data.id');
    $second = $this->postJson('/api/v1/demo-login')->json('data.id');

    expect($first)->not->toBe($second);
    expect(User::where('is_demo', true)->count())->toBe(2);
});

test('expired demo accounts are swept on the next demo login', function (): void {
    $expired = User::factory()->create(['is_demo' => true, 'demo_expires_at' => now()->subHour()]);

    $this->postJson('/api/v1/demo-login')->assertCreated();

    $this->assertModelMissing($expired);
    expect(User::where('is_demo', true)->count())->toBe(1);
});

test('a demo user cannot update their profile or password', function (): void {
    $this->postJson('/api/v1/demo-login');
    $demo = User::where('is_demo', true)->firstOrFail();

    $this->actingAs($demo)
        ->putJson('/api/v1/me', ['name' => 'Hacker', 'email' => 'x@example.com', 'currency' => 'USD', 'timezone' => 'UTC'])
        ->assertUnprocessable();

    $this->actingAs($demo)
        ->putJson('/api/v1/me/password', ['current_password' => 'whatever', 'password' => 'NewPassword123!', 'password_confirmation' => 'NewPassword123!'])
        ->assertUnprocessable();
});

test('demo login is rate limited', function (): void {
    // Each call's controller logs in as the newly-created demo user, and
    // Laravel's auth guard stays resolved across calls within one test
    // process (same quirk noted in AuthenticationTest) — so without logging
    // back out, each call's throttle key would be keyed to the *previous*
    // call's user instead of the one real IP making every request.
    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/v1/demo-login')->assertCreated();
        Auth::guard('web')->logout();
    }

    $this->postJson('/api/v1/demo-login')->assertStatus(429);

    Cache::flush();
});
