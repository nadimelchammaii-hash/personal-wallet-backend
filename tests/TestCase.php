<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Sanctum only treats a request as "from the SPA" (cookie/session
        // auth, CSRF-checked) when Referer/Origin matches a stateful
        // domain. Without this, every API test would look like a
        // token-based request instead of the real frontend traffic it
        // simulates.
        $this->withHeader('Referer', config('app.frontend_url'));
    }
}
