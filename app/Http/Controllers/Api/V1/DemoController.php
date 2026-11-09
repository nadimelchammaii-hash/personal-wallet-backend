<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\DemoDataSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoController extends Controller
{
    /** Kept in sync with the "Demo mode" copy in the frontend app bar. */
    public const int LIFETIME_HOURS = 24;

    public function __construct(protected DemoDataSeeder $demoData) {}

    public function login(Request $request): UserResource
    {
        // Cleanup is lazy rather than scheduled: there is no cron/scheduler
        // process in production, so each demo-login request first sweeps any
        // accounts whose window has passed before creating a new one.
        User::query()->where('is_demo', true)->where('demo_expires_at', '<', now())->get()->each->delete();

        $user = User::create([
            'name' => 'Demo User',
            'email' => 'demo-'.Str::uuid().'@wallet.demo',
            'password' => Hash::make(Str::random(40)),
        ]);
        $user->forceFill([
            'is_demo' => true,
            'demo_expires_at' => now()->addHours(self::LIFETIME_HOURS),
        ])->save();

        // refresh(), not fresh(): currency/timezone are DB-defaulted columns
        // not included in the insert above (same reasoning as register()),
        // and refresh() re-syncs this same instance instead of returning a
        // new one, preserving wasRecentlyCreated so the response is a 201.
        $user->refresh();

        $this->demoData->seed($user);

        Auth::login($user);
        $request->session()->regenerate();

        return new UserResource($user);
    }
}
