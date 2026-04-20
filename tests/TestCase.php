<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RyanChandler\LaravelCloudflareTurnstile\Facades\Turnstile;

abstract class TestCase extends BaseTestCase
{
    //
    protected function setUp(): void
    {
        parent::setUp();

        Turnstile::fake();
    }
}
